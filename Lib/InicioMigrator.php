<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Plugins\FS2017Migrator\Lib;

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\Impuesto;

/**
 * Description of InicioMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class InicioMigrator
{

    /**
     *
     * @var AppSettings
     */
    protected $appSettings;

    /**
     *
     * @var DataBase
     */
    protected $dataBase;

    /**
     *
     * @var Translator
     */
    protected $i18n;

    /**
     *
     * @var Impuesto[]
     */
    protected $impuestos = [];

    /**
     *
     * @var MiniLog
     */
    protected $miniLog;

    public function __construct()
    {
        $this->appSettings = new AppSettings();
        $this->dataBase = new DataBase();
        $this->i18n = new Translator();

        /// Load taxes
        $impuestoModel = new Impuesto();
        foreach ($impuestoModel->all() as $imp) {
            $this->impuestos[$imp->codimpuesto] = $imp;
        }

        $this->miniLog = new MiniLog();
    }

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    public function migrate(&$offset = 0)
    {
        $exclude = [
            'articulo_propiedades', 'attached_files', 'empresas',
            'estados_documentos', 'fs_access', 'fs_extensions2', 'pages',
            'pages_filters', 'pages_options', 'productos', 'roles',
            'roles_access', 'roles_users', 'secuencias_documentos', 'settings',
            'users', 'variantes'
        ];
        foreach ($this->dataBase->getTables() as $tableName) {
            if (!in_array($tableName, $exclude) && !$this->freeTable($tableName)) {
                /// no return
            }
        }

        return true;
    }

    /**
     * 
     * @param string $codimpuesto
     *
     * @return string
     */
    protected function fixImpuesto($codimpuesto)
    {
        return isset($this->impuestos[$codimpuesto]) ? $codimpuesto : null;
    }

    /**
     * 
     * @param string $tableName
     *
     * @return bool
     */
    private function freeTable($tableName)
    {
        $primaryKey = '';

        /// remove constransts (except primary keys)
        foreach ($this->dataBase->getConstraints($tableName, true) as $constraint) {
            if ($constraint['type'] == 'PRIMARY KEY') {
                $primaryKey = $constraint['column_name'];
                continue;
            }

            if (!$this->removeContraint($tableName, $constraint)) {
                return false;
            }
        }

        return $this->removeNotNullColumns($tableName, $primaryKey);
    }

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrateInTransaction(&$offset = 0)
    {
        // start transaction
        $this->dataBase->beginTransaction();
        $return = false;

        try {
            $return = $this->transactionProcess($offset);

            // confirm data
            $this->dataBase->commit();
        } catch (Exception $exp) {
            $this->miniLog->alert($exp->getMessage());
            $return = false;
        } finally {
            if ($this->dataBase->inTransaction()) {
                $this->dataBase->rollback();
            }
        }

        return $return;
    }

    /**
     * 
     * @param string $codejercicio
     * @param string $codparent
     * @param string $codcuenta
     * @param string $descripcion
     * @param string $idcuentaesp
     *
     * @return bool
     */
    protected function newCuenta($codejercicio, $codparent, $codcuenta, $descripcion, $idcuentaesp = null)
    {
        $cuenta = new Cuenta();
        $where = [
            new DataBaseWhere('codcuenta', $codcuenta),
            new DataBaseWhere('codejercicio', $codejercicio)
        ];
        if ($cuenta->loadFromCode('', $where)) {
            return true;
        }

        $cuenta->codcuenta = $codcuenta;
        $cuenta->codejercicio = $codejercicio;
        $cuenta->descripcion = $descripcion;
        $cuenta->codcuentaesp = empty($idcuentaesp) ? null : $idcuentaesp;

        if (!empty($codparent)) {
            $parent = new Cuenta();
            $where2 = [
                new DataBaseWhere('codcuenta', $codparent),
                new DataBaseWhere('codejercicio', $codejercicio)
            ];
            if ($parent->loadFromCode('', $where2)) {
                $cuenta->parent_codcuenta = $parent->codcuenta;
                $cuenta->parent_idcuenta = $parent->idcuenta;
            }
        }

        return $cuenta->save();
    }

    /**
     * 
     * @param string $tableName
     * @param array  $constraint
     *
     * @return bool
     */
    private function removeContraint($tableName, $constraint)
    {
        $sql = '';
        if (strtolower(FS_DB_TYPE) == 'postgresql') {
            $sql .= 'ALTER TABLE ' . $tableName . ' DROP CONSTRAINT ' . $constraint['name'] . ';';
        } elseif ($constraint['type'] == 'FOREIGN KEY') {
            $sql .= 'ALTER TABLE ' . $tableName . ' DROP FOREIGN KEY ' . $constraint['name'] . ';';
        } elseif ($constraint['type'] == 'UNIQUE') {
            $sql .= 'ALTER TABLE ' . $tableName . ' DROP INDEX ' . $constraint['name'] . ';';
        }

        if (!empty($sql) && !$this->dataBase->exec($sql)) {
            $this->miniLog->warning('cant-remove-constraint: ' . $constraint['name']);
            return false;
        }

        return true;
    }

    /**
     * 
     * @param string $tableName
     * @param string $exclude
     */
    private function removeNotNullColumns($tableName, $exclude)
    {
        foreach ($this->dataBase->getColumns($tableName) as $column) {
            if ($column['is_nullable'] == 'YES' || $column['name'] == $exclude) {
                continue;
            }

            $sql = 'ALTER TABLE ' . $tableName . ' MODIFY `' . $column['name'] . '` ' . $column['type'] . ' NULL;';
            if (strtolower(FS_DB_TYPE) == 'postgresql') {
                $sql = 'ALTER TABLE ' . $tableName . ' ALTER COLUMN "' . $column['name'] . '" DROP NOT NULL;';
            }

            if (!$this->dataBase->exec($sql)) {
                $this->miniLog->warning('cant-remove-not-null: ' . $tableName . ' ' . $column['name']);
                return false;
            }
        }

        return true;
    }

    /**
     * 
     * @param string $tableName
     *
     * @return bool
     */
    protected function removeTable($tableName)
    {
        $sql = 'DROP TABLE ' . $tableName . ';';
        return $this->dataBase->exec($sql);
    }

    /**
     * 
     * @param string $tableName
     * @param string $newName
     *
     * @return bool
     */
    protected function renameTable($tableName, $newName)
    {
        if (!$this->dataBase->tableExists($tableName)) {
            return true;
        }

        if ($this->dataBase->tableExists($newName)) {
            $this->removeTable($newName);
        }

        $sql = 'ALTER TABLE ' . $tableName . ' RENAME ' . $newName . ';';
        if (strtolower(FS_DB_TYPE) == 'postgresql') {
            $sql = 'ALTER TABLE ' . $tableName . ' RENAME TO "' . $newName . '";';
        }

        return $this->dataBase->exec($sql);
    }

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function transactionProcess(&$offset = 0)
    {
        return true;
    }
}

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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\CuentaEspecial;
use FacturaScripts\Dinamic\Model\Impuesto;

/**
 * Description of MigratorBase
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
abstract class MigratorBase
{

    /**
     *
     * @var DataBase
     */
    protected $dataBase;

    /**
     *
     * @var Impuesto[]
     */
    protected $impuestos = [];

    abstract protected function migrationProcess(&$offset = 0): bool;

    public function __construct()
    {
        $this->dataBase = new DataBase();

        /// Load taxes
        $impuestoModel = new Impuesto();
        foreach ($impuestoModel->all() as $imp) {
            $this->impuestos[$imp->codimpuesto] = $imp;
        }
    }

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    public function migrate(&$offset = 0)
    {
        // start transaction
        $this->dataBase->beginTransaction();
        $return = false;

        try {
            $return = $this->migrationProcess($offset);

            // confirm data
            $this->dataBase->commit();
        } catch (Exception $exp) {
            $this->toolBox()->log()->alert($exp->getMessage());
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
     * @param bool $disable
     */
    protected function disableForeignKeys($disable = true)
    {
        if (strtolower(FS_DB_TYPE) == 'mysql') {
            $value = $disable ? 0 : 1;
            $this->dataBase->exec('SET FOREIGN_KEY_CHECKS=' . $value . ';');
        }
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
     * @param string $txt
     * @param int    $len
     *
     * @return string
     */
    protected function fixString($txt, $len = 0)
    {
        if (empty($txt)) {
            return $txt;
        }

        $string = $this->toolBox()->utils()->noHtml($txt);
        return empty($len) ? $string : substr($string, 0, $len);
    }

    /**
     * 
     * @param string $code
     *
     * @return string
     */
    protected function getSpecialAccount($code)
    {
        if (empty($code)) {
            return null;
        }

        $specialAccount = new CuentaEspecial();
        if (!$specialAccount->loadFromCode($code)) {
            /// create a new special account
            $specialAccount->codcuentaesp = $code;
            $specialAccount->descripcion = $code;
            $specialAccount->save();
        }

        return $specialAccount->primaryColumnValue();
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
        $cuenta->codcuentaesp = $this->getSpecialAccount($idcuentaesp);

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
     * @return ToolBox
     */
    protected function toolBox()
    {
        return new ToolBox();
    }
}

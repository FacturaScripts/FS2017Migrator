<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use Exception;
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
    /** @var DataBase */
    protected $dataBase;

    /** @var Impuesto[] */
    protected $impuestos = [];

    abstract protected function migrationProcess(int &$offset = 0): bool;

    public function __construct()
    {
        $this->dataBase = new DataBase();

        // Load taxes
        $impuestoModel = new Impuesto();
        foreach ($impuestoModel->all() as $imp) {
            $this->impuestos[$imp->codimpuesto] = $imp;
        }
    }

    public function migrate(int &$offset = 0): bool
    {
        // start transaction
        $this->dataBase->beginTransaction();

        try {
            $return = $this->migrationProcess($offset);

            // confirm data
            $this->dataBase->commit();
        } catch (Exception $exp) {
            $this->toolBox()->log()->error($exp->getMessage());
            $return = false;
        } finally {
            if ($this->dataBase->inTransaction()) {
                $this->dataBase->rollback();
            }
        }

        return $return;
    }

    protected function disableForeignKeys(bool $disable = true): void
    {
        if (strtolower(FS_DB_TYPE) == 'mysql') {
            $value = $disable ? 0 : 1;
            $this->dataBase->exec('SET FOREIGN_KEY_CHECKS=' . $value . ';');
        }
    }

    protected function fixImpuesto(string $codimpuesto): ?string
    {
        return isset($this->impuestos[$codimpuesto]) ? $codimpuesto : null;
    }

    protected function fixString(?string $txt, int $len = 0): string
    {
        if (null === $txt) {
            return '';
        }

        if (empty($txt)) {
            return $txt;
        }

        $string = $this->toolBox()->utils()->noHtml($txt);
        $fixed = preg_replace('/[[:^print:]]/', '', $string);
        return empty($len) ? $fixed : substr($fixed, 0, $len);
    }

    protected function getEmails(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $emails = [];
        $text2 = str_replace([',', ';'], [' ', ' '], $text);
        foreach (explode(' ', $text2) as $aux) {
            $email = trim($aux);
            if (filter_var($aux, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }
        return $emails;
    }

    protected function getSpecialAccount(?string $code): ?string
    {
        if (empty($code)) {
            return null;
        }

        $specialAccount = new CuentaEspecial();
        if (false === $specialAccount->loadFromCode($code)) {
            // create a new special account
            $specialAccount->codcuentaesp = $code;
            $specialAccount->descripcion = $code;
            $specialAccount->save();
        }

        return $specialAccount->primaryColumnValue();
    }

    protected function newCuenta(string $codejercicio, string $codparent, string $codcuenta, string $descripcion, ?string $idcuentaesp = null): bool
    {
        $cuenta = new Cuenta();
        $cuenta->disableAdditionalTest(true);
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

    protected function removeTable(string $tableName): bool
    {
        $sql = 'DROP TABLE ' . $tableName . ';';
        return $this->dataBase->exec($sql);
    }

    protected function renameTable(string $tableName, string $newName): bool
    {
        if (false === $this->dataBase->tableExists($tableName)) {
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

    protected function toolBox(): ToolBox
    {
        return new ToolBox();
    }
}

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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Proveedor;

/**
 * Description of ProveedoresMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ProveedoresMigrator extends InicioMigrator
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    public function migrate(&$offset = 0)
    {
        $proveedorModel = new Proveedor();
        $rows = $proveedorModel->all([], ['codproveedor' => 'ASC'], $offset);
        foreach ($rows as $proveedor) {
            $proveedor->codsubcuenta = $this->getSubcuenta($proveedor->codproveedor);
            $proveedor->email = filter_var($proveedor->email, FILTER_VALIDATE_EMAIL) ? $proveedor->email : '';
            if (!$proveedor->save()) {
                return false;
            }

            if (!$this->migrateAddress($proveedor)) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    /**
     * 
     * @param Proveedor $proveedor
     */
    protected function migrateAddress(&$proveedor)
    {
        $contacto = new Contacto();
        $where = [new DataBaseWhere('codproveedor', $proveedor->codproveedor)];
        if ($contacto->loadFromCode('', $where)) {
            return true;
        }

        $sql = "SELECT * FROM dirproveedores WHERE codproveedor = '" . $proveedor->codproveedor . "';";
        foreach ($this->dataBase->select($sql) as $row) {
            $newContacto = new Contacto();
            foreach ($row as $key => $value) {
                $newContacto->{$key} = $value;
            }

            $newContacto->email = $proveedor->email;
            $newContacto->nombre = $proveedor->nombre;
            if (!$newContacto->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * 
     * @param string $codproveedor
     *
     * @return string
     */
    protected function getSubcuenta($codproveedor)
    {
        $sql = "SELECT * FROM co_subcuentasprov WHERE codproveedor = '" . $codproveedor
            . "' ORDER BY id DESC;";

        foreach ($this->dataBase->select($sql) as $row) {
            return $row['codsubcuenta'];
        }

        return '';
    }
}

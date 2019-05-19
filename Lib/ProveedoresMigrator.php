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
use FacturaScripts\Core\Base\Utils;
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
            $proveedor->telefono1 = strlen($proveedor->telefono1) > 20 ? substr($proveedor->telefono1, 0, 20) : $proveedor->telefono1;
            $proveedor->telefono2 = strlen($proveedor->telefono2) > 20 ? substr($proveedor->telefono2, 0, 20) : $proveedor->telefono2;
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

    /**
     * 
     * @param Proveedor $proveedor
     *
     * @return bool
     */
    protected function migrateAddress(&$proveedor)
    {
        $contacto = new Contacto();
        $where = [new DataBaseWhere('codproveedor', $proveedor->codproveedor)];
        if ($contacto->loadFromCode('', $where)) {
            return true;
        }

        $found = false;
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

            if (Utils::str2bool($row['direccionppal'])) {
                $proveedor->idcontacto = $newContacto->idcontacto;
            }

            $found = true;
        }

        return $found ? $proveedor->save() : $this->newContacto($proveedor);
    }

    /**
     * 
     * @param Proveedor $proveedor
     *
     * @return bool
     */
    protected function newContacto(&$proveedor)
    {
        $contact = new Contacto();
        $contact->cifnif = $proveedor->cifnif;
        $contact->codproveedor = $proveedor->codproveedor;
        $contact->descripcion = $proveedor->nombre;
        $contact->email = $proveedor->email;
        $contact->empresa = $proveedor->razonsocial;
        $contact->fax = $proveedor->fax;
        $contact->nombre = $proveedor->nombre;
        $contact->personafisica = $proveedor->personafisica;
        $contact->telefono1 = $proveedor->telefono1;
        $contact->telefono2 = $proveedor->telefono2;
        if ($contact->save()) {
            $proveedor->idcontacto = $contact->idcontacto;
            return $proveedor->save();
        }

        return false;
    }
}

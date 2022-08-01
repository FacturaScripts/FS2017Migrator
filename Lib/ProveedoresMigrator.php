<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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
class ProveedoresMigrator extends MigratorBase
{

    protected function fixProveedores()
    {
        $sql = "UPDATE proveedores SET codpago = null WHERE codpago NOT IN (SELECT codpago FROM formaspago);"
            . "UPDATE proveedores SET codserie = null WHERE codserie NOT IN (SELECT codserie FROM series);";
        $this->dataBase->exec($sql);
    }

    /**
     *
     * @param string $codproveedor
     *
     * @return string
     */
    protected function getSubcuenta($codproveedor)
    {
        if (false === $this->dataBase->tableExists('co_subcuentasprov')) {
            return '';
        }

        $sql = "SELECT * FROM co_subcuentasprov WHERE codproveedor = " . $this->dataBase->var2str($codproveedor) . " ORDER BY id DESC;";
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
        if ($contacto->loadFromCode('', $where) || false === $this->dataBase->tableExists('dirproveedores')) {
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
            if (false === $newContacto->save()) {
                return false;
            }

            if ($this->toolBox()->utils()->str2bool($row['direccionppal'])) {
                $proveedor->idcontacto = $newContacto->idcontacto;
            }

            $found = true;
        }

        return $found ? $proveedor->save() : $this->newContacto($proveedor);
    }

    /**
     *
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (0 === $offset) {
            $this->fixProveedores();
        }

        $proveedorModel = new Proveedor();
        $rows = $proveedorModel->all([], ['codproveedor' => 'ASC'], $offset);
        foreach ($rows as $proveedor) {
            $proveedor->codsubcuenta = $this->getSubcuenta($proveedor->codproveedor);
            $proveedor->telefono1 = $this->fixString($proveedor->telefono1, 20);
            $proveedor->telefono2 = $this->fixString($proveedor->telefono2, 20);

            $emails = $this->getEmails($proveedor->email);
            $proveedor->email = empty($emails) ? '' : $emails[0];
            foreach ($emails as $num => $email) {
                if ($num > 0) {
                    $proveedor->observaciones .= "\n" . $email;
                }
            }

            if (isset($proveedor->debaja) && false == $proveedor->debaja) {
                $proveedor->fechabaja = null;
            }

            if ($proveedor->web && false === Utils::isValidUrl($proveedor->web)) {
                $proveedor->web = '';
            }

            if (false === $proveedor->save()) {
                return false;
            }

            if (false === $this->migrateAddress($proveedor)) {
                return false;
            }

            $offset++;
        }

        return true;
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

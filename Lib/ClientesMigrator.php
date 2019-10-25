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
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;

/**
 * Description of ClientesMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ClientesMigrator extends MigratorBase
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (0 === $offset) {
            $this->fixClientes();
        }

        $clienteModel = new Cliente();
        $rows = $clienteModel->all([], ['codcliente' => 'ASC'], $offset);
        foreach ($rows as $cliente) {
            $cliente->codsubcuenta = $this->getSubcuenta($cliente->codcliente);
            $cliente->email = filter_var($cliente->email, FILTER_VALIDATE_EMAIL) ? $cliente->email : '';
            $cliente->telefono1 = $this->fixString($cliente->telefono1, 20);
            $cliente->telefono2 = $this->fixString($cliente->telefono2, 20);
            if (!$cliente->save()) {
                return false;
            }

            if (!$this->migrateAddress($cliente)) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    protected function fixClientes()
    {
        $sql = "UPDATE clientes SET codpago = null WHERE codpago NOT IN (SELECT codpago FROM formaspago);"
            . "UPDATE clientes SET codserie = null WHERE codserie NOT IN (SELECT codserie FROM series);";
        $this->dataBase->exec($sql);
    }

    /**
     * 
     * @param string $codcliente
     *
     * @return string
     */
    protected function getSubcuenta($codcliente)
    {
        if (!$this->dataBase->tableExists('co_subcuentascli')) {
            return '';
        }

        $sql = "SELECT * FROM co_subcuentascli WHERE codcliente = " . $this->dataBase->var2str($codcliente) . " ORDER BY id DESC;";
        foreach ($this->dataBase->select($sql) as $row) {
            return $row['codsubcuenta'];
        }

        return '';
    }

    /**
     * 
     * @param Cliente $cliente
     *
     * @return bool
     */
    protected function migrateAddress(&$cliente)
    {
        $contacto = new Contacto();
        $where = [new DataBaseWhere('codcliente', $cliente->codcliente)];
        if ($contacto->loadFromCode('', $where)) {
            return true;
        }

        $found = false;
        $sql = "SELECT * FROM dirclientes WHERE codcliente = '" . $cliente->codcliente . "';";
        foreach ($this->dataBase->select($sql) as $row) {
            $newContacto = new Contacto();
            foreach ($row as $key => $value) {
                $newContacto->{$key} = $value;
            }

            $newContacto->email = $cliente->email;
            $newContacto->nombre = $cliente->nombre;
            if (!$newContacto->save()) {
                return false;
            }

            if ($this->toolBox()->utils()->str2bool($row['domfacturacion'])) {
                $cliente->idcontactofact = $newContacto->idcontacto;
            }

            if ($this->toolBox()->utils()->str2bool($row['domenvio'])) {
                $cliente->idcontactoenv = $newContacto->idcontacto;
            }

            $found = true;
        }

        return $found ? $cliente->save() : $this->newContacto($cliente);
    }

    /**
     * 
     * @param Cliente $cliente
     *
     * @return bool
     */
    protected function newContacto(&$cliente)
    {
        $contact = new Contacto();
        $contact->cifnif = $cliente->cifnif;
        $contact->codcliente = $cliente->codcliente;
        $contact->descripcion = $cliente->nombre;
        $contact->email = $cliente->email;
        $contact->empresa = $cliente->razonsocial;
        $contact->fax = $cliente->fax;
        $contact->nombre = $cliente->nombre;
        $contact->personafisica = $cliente->personafisica;
        $contact->telefono1 = $cliente->telefono1;
        $contact->telefono2 = $cliente->telefono2;
        if ($contact->save()) {
            $cliente->idcontactoenv = $contact->idcontacto;
            $cliente->idcontactofact = $contact->idcontacto;
            return $cliente->save();
        }

        return false;
    }
}

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
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;

/**
 * Description of ClienteMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ClienteMigrator extends MigratorBase
{

    /**
     * 
     * @param int $offset
     *
     * @return int
     */
    public function migrate($offset = 0)
    {
        $newOffset = 0;

        $clienteModel = new Cliente();
        foreach ($clienteModel->all([], ['codcliente' => 'ASC'], $offset) as $cliente) {
            $cliente->codsubcuenta = $this->getSubcuenta($cliente->codsubcuenta);
            $cliente->email = filter_var($cliente->email, FILTER_VALIDATE_EMAIL) ? $cliente->email : '';
            $cliente->save();

            $this->migrateAddress($cliente);

            $newOffset += empty($newOffset) ? 1 + $offset : 1;
        }

        return $newOffset;
    }

    /**
     * 
     * @param Cliente $cliente
     */
    protected function migrateAddress(&$cliente)
    {
        $contacto = new Contacto();
        $where = [new DataBaseWhere('codcliente', $cliente->codcliente)];
        if ($contacto->loadFromCode('', $where)) {
            return;
        }

        $sql = "SELECT * FROM dirclientes WHERE codcliente = '" . $cliente->codcliente . "';";
        foreach ($this->dataBase->select($sql) as $row) {
            $newContacto = new Contacto();
            foreach ($row as $key => $value) {
                $newContacto->{$key} = $value;
            }

            $newContacto->email = $cliente->email;
            $newContacto->nombre = $cliente->nombre;
            $newContacto->save();

            if (Utils::str2bool($row['domfacturacion'])) {
                $cliente->idcontactofact = $newContacto->idcontacto;
            }

            if (Utils::str2bool($row['domenvio'])) {
                $cliente->idcontactoenv = $newContacto->idcontacto;
            }
        }

        $cliente->save();
    }

    /**
     * 
     * @param string $codcliente
     *
     * @return string
     */
    protected function getSubcuenta($codcliente)
    {
        $sql = "SELECT * FROM co_subcuentascli WHERE codcliente = '" . $codcliente
            . "' ORDER BY id DESC;";

        foreach ($this->dataBase->select($sql) as $row) {
            return $row['codsubcuenta'];
        }

        return '';
    }
}

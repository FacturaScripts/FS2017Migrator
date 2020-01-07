<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019-2020 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Dinamic\Model\CrmFuente;

/**
 * Description of ContactosMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ContactosMigrator extends MigratorBase
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if ($this->dataBase->tableExists('crm_contactos')) {
            $sql = "SELECT * FROM crm_contactos ORDER BY codcontacto ASC";
            $rows = $this->dataBase->selectLimit($sql, 300, $offset);
            foreach ($rows as $row) {
                if (!$this->newContact($row)) {
                    return false;
                }

                $offset++;
            }
        }

        return true;
    }

    /**
     * 
     * @param string $name
     *
     * @return int
     */
    protected function getIdFuente($name)
    {
        $fuente = new CrmFuente();
        $where = [new DataBaseWhere('nombre', $this->toolBox()->utils()->noHtml($name))];
        if (!$fuente->loadFromCode('', $where)) {
            /// create source
            $fuente->descripcion = $name;
            $fuente->nombre = $name;
            $fuente->save();
        }

        return $fuente->primaryColumnValue();
    }

    /**
     * 
     * @param array $data
     *
     * @return bool
     */
    protected function newContact($data)
    {
        $where = empty($data['email']) ? [new DataBaseWhere('nombre', $data['nombre'])] : [new DataBaseWhere('email', $data['email'])];
        $contacto = new Contacto();
        if ($contacto->loadFromCode('', $where)) {
            return true;
        }

        $data['cifnif'] = $data['nif'] ?? '';
        if (empty($data['nombre']) && empty($data['direccion'])) {
            $data['descripcion'] = $data['codcontacto'];
        }
        $data['email'] = filter_var($data['email'], FILTER_VALIDATE_EMAIL) ? $data['email'] : '';

        $contacto->loadFromData($data);
        if (isset($data['fuente'])) {
            $contacto->idfuente = $this->getIdFuente($data['fuente']);
        }

        return $contacto->save();
    }
}

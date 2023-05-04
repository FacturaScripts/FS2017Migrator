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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\User;

class UsersMigrator extends MigratorBase
{
    /**
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (false === $this->dataBase->tableExists('fs_users')) {
            return true;
        }

        $sql = "SELECT * FROM fs_users;";
        foreach ($this->dataBase->select($sql) as $row) {
            // si no hay email o no es válido, saltamos
            if (empty($row['email']) || false === filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // comprobamos si ya hay un usuario con ese email
            $user = new User();
            $where = [new DataBaseWhere('email', $row['email'])];
            if ($user->loadFromCode('', $where)) {
                continue;
            }

            // no lo encontramos, lo creamos
            $user->admin = in_array($row['admin'], ['1', 't']);
            $user->email = $row['email'];
            $user->newPassword = $user->newPassword2 = Tools::randomString();
            $user->nick = $row['nick'];
            if (false === $user->save()) {
                return false;
            }
        }

        return true;
    }
}
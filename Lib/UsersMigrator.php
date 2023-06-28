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

use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Dinamic\Model\User;

class UsersMigrator extends MigratorBase
{
    protected function migrationProcess(int &$offset = 0): bool
    {
        if (false === $this->dataBase->tableExists('fs_users')) {
            return true;
        }

        $sql = "SELECT * FROM fs_users;";
        foreach ($this->dataBase->select($sql) as $row) {
            // comprobamos si ya hay un usuario con ese nick
            $user = new User();
            if ($user->loadFromCode($row['nick'])) {
                continue;
            }

            // no lo encontramos, lo creamos
            $user->admin = in_array($row['admin'], ['1', 't']);

            // si hay email y es válido, lo usamos
            if ($row['email'] && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $user->email = $row['email'];
            }

            $user->newPassword = $user->newPassword2 = Utils::randomString(8);
            $user->nick = $row['nick'];
            if (false === $user->save()) {
                return false;
            }
        }

        return true;
    }
}
<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2020 Carlos Garcia Gomez <carlos@facturascripts.com>
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

/**
 * Description of MysqlMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class MysqlMigrator extends MigratorBase
{

    /**
     * 
     * @param string $tableName
     *
     * @return bool
     */
    private function fixOldMysqlPKs($tableName): bool
    {
        foreach ($this->dataBase->getColumns($tableName) as $colData) {
            if ($colData['type'] != 'int unsigned') {
                continue;
            }

            $sql = 'ALTER TABLE `' . $tableName . '` MODIFY `' . $colData['name'] . '` INTEGER;';
            if (false === $this->dataBase->exec($sql)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (\strtolower(FS_DB_TYPE) != 'mysql') {
            return true;
        }

        $exclude = [
            'attached_files', 'cajas', 'crm_calendario', 'empresas', 'estados_documentos',
            'fs_access', 'fs_extensions2', 'pages', 'pages_filters',
            'pages_options', 'productos', 'roles', 'roles_access',
            'roles_users', 'secuencias_documentos', 'settings', 'users',
            'variantes'
        ];
        $tables = [];
        foreach ($this->dataBase->getTables() as $tableName) {
            if (false === \in_array($tableName, $exclude)) {
                $tables[] = $tableName;
            }
        }

        foreach ($tables as $num => $tableName) {
            if ($num != $offset) {
                continue;
            }

            if ($this->fixOldMysqlPKs($tableName)) {
                $offset++;
                return true;
            }

            return false;
        }

        return true;
    }
}

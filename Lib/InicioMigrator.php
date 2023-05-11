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

use FacturaScripts\Core\Cache;

/**
 * Description of InicioMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class InicioMigrator extends MigratorBase
{
    /** @var array */
    private $removedConstraints = [];

    /** @return array */
    private function getTables(): array
    {
        $exclude = [
            'attached_files', 'cajas', 'cajas_general', 'cajas_general_mov', 'crm_calendario', 'empresas',
            'estados_documentos', 'fs_access', 'fs_extensions2', 'pages', 'pages_filters', 'pages_options',
            'productos', 'roles', 'roles_access', 'roles_users', 'secuencias_documentos', 'settings', 'users',
            'variantes'
        ];
        $tables = [];
        foreach ($this->dataBase->getTables() as $tableName) {
            if (in_array($tableName, $exclude)) {
                continue;
            }

            switch ($tableName) {
                case 'beneficios':
                    array_unshift($tables, $tableName);
                    break;

                default:
                    $tables[] = $tableName;
                    break;
            }
        }

        return $tables;
    }

    protected function migrationProcess(int &$offset = 0): bool
    {
        if (0 === $offset && false === $this->dataBase->tableExists('fs_vars')) {
            $this->toolBox()->i18nLog()->warning('no-db-2017');
            return false;
        } elseif (0 === $offset) {
            Cache::clear();
        }

        $this->disableForeignKeys(true);
        foreach ($this->getTables() as $num => $tableName) {
            if ($num != $offset) {
                continue;
            }

            if ($this->removeForeignKeys($tableName) &&
                $this->removeUniques($tableName) &&
                $this->removeNotNulls($tableName)) {
                $offset++;
                return true;
            }

            return false;
        }

        $this->disableForeignKeys(false);
        return true;
    }

    private function removeForeignKeys(string $tableName): bool
    {
        foreach ($this->dataBase->getConstraints($tableName, true) as $constraint) {
            if ($constraint['type'] == 'PRIMARY KEY' || in_array($constraint['name'], $this->removedConstraints)) {
                continue;
            }

            $sql = '';
            if (strtolower(FS_DB_TYPE) == 'postgresql') {
                $sql .= 'ALTER TABLE ' . $tableName . ' DROP CONSTRAINT ' . $constraint['name'] . ';';
            } elseif ($constraint['type'] == 'FOREIGN KEY') {
                $sql .= 'ALTER TABLE ' . $tableName . ' DROP FOREIGN KEY ' . $constraint['name'] . ';';
            } else {
                continue;
            }

            $this->removedConstraints[] = $constraint['name'];

            if ($sql && false === $this->dataBase->exec($sql)) {
                $this->toolBox()->log()->warning('cant-remove-constraint: ' . $constraint['name']);
                return false;
            }
        }

        return true;
    }

    private function removeNotNulls(string $tableName): bool
    {
        $primaryKey = [];
        foreach ($this->dataBase->getConstraints($tableName, true) as $constraint) {
            if ($constraint['type'] == 'PRIMARY KEY') {
                $primaryKey[] = $constraint['column_name'];
            }
        }

        foreach ($this->dataBase->getColumns($tableName) as $column) {
            if ($column['is_nullable'] == 'YES' || in_array($column['name'], $primaryKey)) {
                continue;
            }

            $sql = strtolower(FS_DB_TYPE) == 'postgresql' ?
                'ALTER TABLE ' . $tableName . ' ALTER COLUMN "' . $column['name'] . '" DROP NOT NULL;' :
                'ALTER TABLE ' . $tableName . ' MODIFY `' . $column['name'] . '` ' . $column['type'] . ' NULL;';

            if (false === $this->dataBase->exec($sql)) {
                $this->toolBox()->log()->warning('cant-remove-not-null: ' . $tableName . ' ' . $column['name']);
                return false;
            }
        }

        return true;
    }

    private function removeUniques(string $tableName): bool
    {
        foreach ($this->dataBase->getConstraints($tableName, true) as $constraint) {
            if ($constraint['type'] == 'PRIMARY KEY' || in_array($constraint['name'], $this->removedConstraints)) {
                continue;
            }

            $sql = '';
            if (strtolower(FS_DB_TYPE) == 'postgresql') {
                $sql .= 'ALTER TABLE ' . $tableName . ' DROP CONSTRAINT ' . $constraint['name'] . ';';
            } elseif ($constraint['type'] == 'UNIQUE') {
                $sql .= 'ALTER TABLE ' . $tableName . ' DROP INDEX ' . $constraint['name'] . ';';
            } else {
                continue;
            }

            $this->removedConstraints[] = $constraint['name'];

            if ($sql && false === $this->dataBase->exec($sql)) {
                $this->toolBox()->log()->warning('cant-remove-constraint: ' . $constraint['name']);
                return false;
            }
        }

        return true;
    }
}

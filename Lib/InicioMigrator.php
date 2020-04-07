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

/**
 * Description of InicioMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class InicioMigrator extends MigratorBase
{

    /**
     *
     * @var array
     */
    private $removedConstraints = [];

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        $exclude = [
            'attached_files', 'cajas', 'empresas',
            'estados_documentos', 'fs_access', 'fs_extensions2', 'pages',
            'pages_filters', 'pages_options', 'productos', 'roles',
            'roles_access', 'roles_users', 'secuencias_documentos', 'settings',
            'users', 'variantes'
        ];

        $this->disableForeignKeys(true);
        foreach ($this->dataBase->getTables() as $tableName) {
            if (\in_array($tableName, $exclude)) {
                continue;
            }

            if (0 == $offset) {
                $return = $this->removeForeignKeys($tableName);
            } elseif (1 == $offset) {
                $return = $this->removeUniques($tableName);
            } else {
                $return = $this->removeNotNulls($tableName);
            }

            if (false === $return) {
                return false;
            }
        }

        $this->disableForeignKeys(false);
        if ($offset < 2) {
            $offset++;
        }

        return true;
    }

    /**
     * 
     * @param string $tableName
     *
     * @return bool
     */
    private function removeForeignKeys($tableName)
    {
        foreach ($this->dataBase->getConstraints($tableName, true) as $constraint) {
            if ($constraint['type'] == 'PRIMARY KEY' || \in_array($constraint['name'], $this->removedConstraints)) {
                continue;
            }

            $sql = '';
            if (\strtolower(FS_DB_TYPE) == 'postgresql') {
                $sql .= 'ALTER TABLE ' . $tableName . ' DROP CONSTRAINT ' . $constraint['name'] . ';';
            } elseif ($constraint['type'] == 'FOREIGN KEY') {
                $sql .= 'ALTER TABLE ' . $tableName . ' DROP FOREIGN KEY ' . $constraint['name'] . ';';
            } else {
                continue;
            }

            $this->removedConstraints[] = $constraint['name'];

            if (!empty($sql) && !$this->dataBase->exec($sql)) {
                $this->toolBox()->log()->warning('cant-remove-constraint: ' . $constraint['name']);
                return false;
            }
        }

        return true;
    }

    /**
     * 
     * @param string $tableName
     *
     * @return bool
     */
    private function removeNotNulls($tableName)
    {
        $primaryKey = [];
        foreach ($this->dataBase->getConstraints($tableName, true) as $constraint) {
            if ($constraint['type'] == 'PRIMARY KEY') {
                $primaryKey[] = $constraint['column_name'];
            }
        }

        foreach ($this->dataBase->getColumns($tableName) as $column) {
            if ($column['is_nullable'] == 'YES' || \in_array($column['name'], $primaryKey)) {
                continue;
            }

            $sql = 'ALTER TABLE ' . $tableName . ' MODIFY `' . $column['name'] . '` ' . $column['type'] . ' NULL;';
            if (\strtolower(FS_DB_TYPE) == 'postgresql') {
                $sql = 'ALTER TABLE ' . $tableName . ' ALTER COLUMN "' . $column['name'] . '" DROP NOT NULL;';
            }

            if (!$this->dataBase->exec($sql)) {
                $this->toolBox()->log()->warning('cant-remove-not-null: ' . $tableName . ' ' . $column['name']);
                return false;
            }
        }

        return true;
    }

    /**
     * 
     * @param string $tableName
     *
     * @return bool
     */
    private function removeUniques($tableName)
    {
        foreach ($this->dataBase->getConstraints($tableName, true) as $constraint) {
            if ($constraint['type'] == 'PRIMARY KEY' || \in_array($constraint['name'], $this->removedConstraints)) {
                continue;
            }

            $sql = '';
            if (\strtolower(FS_DB_TYPE) == 'postgresql') {
                $sql .= 'ALTER TABLE ' . $tableName . ' DROP CONSTRAINT ' . $constraint['name'] . ';';
            } elseif ($constraint['type'] == 'UNIQUE') {
                $sql .= 'ALTER TABLE ' . $tableName . ' DROP INDEX ' . $constraint['name'] . ';';
            } else {
                continue;
            }

            $this->removedConstraints[] = $constraint['name'];

            if (!empty($sql) && !$this->dataBase->exec($sql)) {
                $this->toolBox()->log()->warning('cant-remove-constraint: ' . $constraint['name']);
                return false;
            }
        }

        return true;
    }
}

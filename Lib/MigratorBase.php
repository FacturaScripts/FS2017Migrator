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

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\Cache;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Base\Translator;

/**
 * Description of MigratorBase
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
abstract class MigratorBase
{

    /**
     *
     * @var AppSettings
     */
    protected $appSettings;

    /**
     *
     * @var Cache
     */
    protected $cache;

    /**
     *
     * @var DataBase
     */
    protected $dataBase;

    /**
     *
     * @var Translator
     */
    protected $i18n;

    /**
     *
     * @var MiniLog
     */
    protected $miniLog;

    abstract public function migrate($offset = 0);

    public function __construct()
    {
        $this->appSettings = new AppSettings();
        $this->cache = new Cache();
        $this->dataBase = new DataBase();
        $this->i18n = new Translator();
        $this->miniLog = new MiniLog();
    }

    public function freeTables()
    {
        $exclude = [
            'articulo_propiedades', 'attached_files', 'empresas',
            'estados_documentos', 'fs_access', 'fs_extensions2', 'pages',
            'pages_filters', 'pages_options', 'productos', 'roles',
            'roles_access', 'roles_users', 'secuencias_documentos', 'settings',
            'users', 'variantes'
        ];
        foreach ($this->dataBase->getTables() as $tableName) {
            if (!in_array($tableName, $exclude)) {
                $this->freeTable($tableName);
            }
        }

        $this->cache->clear();
    }

    /**
     * 
     * @param string $tableName
     */
    private function freeTable($tableName)
    {
        $primaryKey = '';

        /// remove constransts (except primary keys)
        foreach ($this->dataBase->getConstraints($tableName, true) as $constraint) {
            if ($constraint['type'] == 'PRIMARY KEY') {
                $primaryKey = $constraint['column_name'];
                continue;
            }

            $this->removeContraint($tableName, $constraint);
        }

        $this->removeNotNullColumns($tableName, $primaryKey);
    }

    /**
     * 
     * @param string $tableName
     * @param array  $constraint
     */
    private function removeContraint($tableName, $constraint)
    {
        if (strtolower(FS_DB_TYPE) == 'postgresql') {
            $sql = 'ALTER TABLE ' . $tableName . ' DROP CONSTRAINT ' . $constraint['name'] . ';';
        }

        if (!$this->dataBase->exec($sql)) {
            $this->miniLog->warning('cant-remove-constraint: ' . $constraint['name']);
        }
    }

    /**
     * 
     * @param string $tableName
     * @param string $exclude
     */
    private function removeNotNullColumns($tableName, $exclude)
    {
        foreach ($this->dataBase->getColumns($tableName) as $column) {
            if ($column['is_nullable'] == 'YES' || $column['name'] == $exclude) {
                continue;
            }

            $sql = 'ALTER TABLE ' . $tableName . ' MODIFY `' . $column['name'] . '` ' . $column['type'] . ' NULL;';
            if (strtolower(FS_DB_TYPE) == 'postgresql') {
                $sql = 'ALTER TABLE ' . $tableName . ' ALTER COLUMN "' . $column['name'] . '" DROP NOT NULL;';
            }

            if (!$this->dataBase->exec($sql)) {
                $this->miniLog->warning('cant-remove-not-null: ' . $tableName . ' ' . $column['name']);
            }
        }
    }

    /**
     * 
     * @param string $tableName
     * @param string $newName
     *
     * @return bool
     */
    protected function renameTable($tableName, $newName)
    {
        $sql = 'ALTER TABLE ' . $tableName . ' RENAME "' . $newName . '";';
        if (strtolower(FS_DB_TYPE) == 'postgresql') {
            $sql = 'ALTER TABLE ' . $tableName . ' RENAME TO "' . $newName . '";';
        }

        return $this->dataBase->exec($sql);
    }
}

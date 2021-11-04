<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2021 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Base\FileManager;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;

/**
 * Description of FilesProCliMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class FilesProCliMigrator extends MigratorBase
{

    const FOLDER_NAME = 'documentos_procli';

    /**
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        $tmpFolder = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR . 'FS2017Migrator' . DIRECTORY_SEPARATOR . 'tmp';
        if (false === file_exists($tmpFolder)) {
            return true;
        }

        foreach (FileManager::scanFolder($tmpFolder, true) as $file) {
            $path = explode(DIRECTORY_SEPARATOR, $file);
            if (count($path) !== 5) {
                continue;
            }

            if (false === $this->moveFile($tmpFolder . DIRECTORY_SEPARATOR . $file, $path[4], $path[2], $path[3])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $fromPath
     * @param string $fileName
     * @param string $modelName
     * @param string $modelCode
     *
     * @return bool
     */
    private function moveFile(string $fromPath, string $fileName, string $modelName, string $modelCode): bool
    {
        $newPath = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR . $fileName;
        if (false === rename($fromPath, $newPath)) {
            return false;
        }

        $newAttFile = new AttachedFile();
        $newAttFile->path = $fileName;
        if (false === $newAttFile->save()) {
            return false;
        }

        switch ($modelName) {
            case 'cliente':
                return $this->newRelation($newAttFile->idfile, 'Cliente', $modelCode);

            case 'proveedor':
                return $this->newRelation($newAttFile->idfile, 'Proveedor', $modelCode);
        }

        return false;
    }

    /**
     * @param int $idfile
     * @param string $model
     * @param string $modelcode
     *
     * @return bool
     */
    private function newRelation($idfile, $model, $modelcode): bool
    {
        $newRelation = new AttachedFileRelation();
        $newRelation->idfile = $idfile;
        $newRelation->model = $model;
        $newRelation->modelcode = $modelcode;
        $newRelation->modelid = (int)$modelcode;
        return $newRelation->save();
    }
}

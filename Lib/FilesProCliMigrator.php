<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2021-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Tools;
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

    protected function migrationProcess(int &$offset = 0): bool
    {
        $tmpFolder = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR . 'FS2017Migrator' . DIRECTORY_SEPARATOR . 'tmp';
        if (false === file_exists($tmpFolder)) {
            return true;
        }

        foreach (Tools::folderScan($tmpFolder, true) as $file) {
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

    private function newRelation(int $idfile, string $model, string $modelcode): bool
    {
        $newRelation = new AttachedFileRelation();
        $newRelation->idfile = $idfile;
        $newRelation->model = $model;
        $newRelation->modelcode = $modelcode;
        $newRelation->modelid = (int)$modelcode;

        return $newRelation->save();
    }
}

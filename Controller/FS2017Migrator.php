<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
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
namespace FacturaScripts\Plugins\FS2017Migrator\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\FileManager;
use ZipArchive;

/**
 * Description of FS2017Migrator
 *
 * @author Carlos García Gómez  <carlos@facturascripts.com>
 */
class FS2017Migrator extends Controller
{

    /**
     *
     * @var bool
     */
    public $enableRun = true;

    /**
     *
     * @var array
     */
    public $migrationLog = [];

    /**
     *
     * @var int
     */
    public $offset;

    /**
     *
     * @var bool
     */
    public $working = false;

    /**
     * 
     * @return array
     */
    public function getPageData()
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['submenu'] = 'control-panel';
        $data['title'] = '2017-migrator';
        $data['icon'] = 'fas fa-database';
        return $data;
    }

    /**
     * 
     * @return bool
     */
    public function findFileBackup(): bool
    {
        $path = 'MyFiles' . DIRECTORY_SEPARATOR . 'FS2017Migrator';
        if (false === \file_exists($path)) {
            FileManager::createFolder($path);
            return false;
        }

        foreach (FileManager::scanFolder($path) as $file) {
            if ('.zip' === \substr($file, -4)) {
                return $this->extractBackup($file);
            }
        }

        return \file_exists(\FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR
            . 'FS2017Migrator' . DIRECTORY_SEPARATOR . 'FOUND.lock');
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->offset = (int) $this->request->get('offset', '0');

        $action = $this->request->get('action', '');
        switch ($action) {
            case '':
                $this->findFileBackup();
                break;

            case 'remove-backup':
                $this->removeBackupAction();
                break;

            default:
                $this->enableRun = false;
                $this->executeStep($action);
                break;
        }
    }

    /**
     * 
     * @param string $name
     */
    private function executeStep($name)
    {
        $this->working = true;
        $steps = [
            'Inicio', 'Mysql', 'Empresa', 'GruposEpigrafes', 'Epigrafes',
            'Cuentas', 'Subcuentas', 'Balances', 'Asientos', 'Tarifas', 'Clientes',
            'Proveedores', 'Atributos', 'Productos', 'PedidosProveedor',
            'AlbaranesProveedor', 'FacturasProveedor', 'RecibosProveedor',
            'PagosProveedor', 'PresupuestosCliente', 'PedidosCliente',
            'AlbaranesCliente', 'FacturasCliente', 'RecibosCliente',
            'PagosCliente', 'Estados', 'Secuencias', 'Contactos',
            'RegImpuestos', 'Files', 'FilesExpedientes', 'FilesProCli',
            'Servicios', 'end'
        ];

        $next = false;
        foreach ($steps as $step) {
            if ($next) {
                $this->toolBox()->cache()->clear();

                /// redirect to next step
                $this->redirect($this->url() . '?action=' . $step, 1);
                break;
            } elseif ($name != $step) {
                /// step done
                $this->migrationLog[] = $step;
                continue;
            }

            $this->migrationLog[] = empty($this->offset) ? $step : $step . ' (' . $this->offset . ')';

            /// selected step
            $next = true;
            if ($step == 'end') {
                $this->toolBox()->cache()->clear();
                $this->working = false;
                break;
            }

            $initial = $this->offset;
            $className = '\\FacturaScripts\\Plugins\\FS2017Migrator\\Lib\\' . $step . 'Migrator';
            $migrator = new $className();
            if (!$migrator->migrate($this->offset)) {
                /// migration error
                $this->working = false;
                break;
            } elseif ($this->offset > $initial) {
                /// reload with next offset
                $this->redirect($this->url() . '?action=' . $step . '&offset=' . $this->offset, 1);
                break;
            }
        }
    }

    /**
     * 
     * @param string $fileName
     *
     * @return bool
     */
    private function extractBackup(string $fileName): bool
    {
        $zip = new ZipArchive();
        $filePath = \FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR . 'FS2017Migrator' . DIRECTORY_SEPARATOR . $fileName;
        $zipStatus = $zip->open($filePath, ZipArchive::CHECKCONS);
        if ($zipStatus !== true) {
            $this->toolBox()->log()->critical('ZIP ERROR: ' . $zipStatus);
            return false;
        }

        if (false === $zip->extractTo(\FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR . 'FS2017Migrator')) {
            $this->toolBox()->log()->critical('ZIP EXTRACT ERROR: ' . $fileName);
            $zip->close();
            return false;
        }

        \unlink($filePath);
        \touch(\FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR . 'FS2017Migrator' . DIRECTORY_SEPARATOR . 'FOUND.lock');
        return true;
    }

    private function removeBackupAction()
    {
        $path = 'MyFiles' . DIRECTORY_SEPARATOR . 'FS2017Migrator';
        FileManager::delTree($path);
    }
}

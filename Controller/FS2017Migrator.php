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
namespace FacturaScripts\Plugins\FS2017Migrator\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Plugins\FS2017Migrator\Lib\EmpresaMigrator;

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
        $data['icon'] = 'fas fa-database';
        $data['menu'] = 'admin';
        $data['submenu'] = 'control-panel';
        $data['title'] = '2017-migrator';

        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->offset = (int) $this->request->get('offset', '0');

        $action = $this->request->get('action', '');
        switch ($action) {
            case '':
                break;

            default:
                $this->enableRun = false;
                $this->executeStep($action);
                break;
        }
    }

    private function executeStep($name)
    {
        $this->working = true;
        $steps = [
            'start', 'Empresa', 'Producto', 'end'
        ];

        $found = false;
        foreach ($steps as $step) {
            if ($found) {
                /// redirect to next step
                $this->redirect($this->url() . '?action=' . $step, 2);
                break;
            } elseif ($name != $step) {
                /// step done
                $this->migrationLog[] = $step;
                continue;
            }

            $this->migrationLog[] = empty($this->offset) ? $step : $step . ' (' . $this->offset . ')';

            /// selected step
            $found = true;
            if ($step == 'start') {
                $migrator = new EmpresaMigrator();
                $migrator->freeTables();
                continue;
            } elseif ($step == 'end') {
                $this->working = false;
                break;
            }

            $className = 'FacturaScripts\\Plugins\\FS2017Migrator\\Lib\\' . $step . 'Migrator';
            $migrator = new $className();
            $offset = $migrator->migrate($this->offset);
            if ($offset > 0) {
                /// reload with newxt offset
                $this->redirect($this->url() . '?action=' . $step . '&offset=' . $offset, 2);
                break;
            }
        }
    }
}

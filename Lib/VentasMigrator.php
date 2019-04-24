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

use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;

/**
 * Description of VentasMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class VentasMigrator extends ComprasMigrator
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    public function migrate(&$offset = 0)
    {
        switch ($offset) {
            case 0:
                $offset++;
                return $this->fixLinesTable('lineaspresupuestoscli');

            case 1:
                $offset++;
                return $this->fixLinesTable('lineaspedidoscli');

            case 2:
                $offset++;
                return $this->fixLinesTable('lineasalbaranescli');

            case 3:
                $offset++;
                return $this->fixLinesTable('lineasfacturascli');

            case 4:
                $offset++;
                new PresupuestoCliente();
                break;

            case 5:
                $offset++;
                new PedidoCliente();
                break;

            case 6:
                $offset++;
                new AlbaranCliente();
                break;

            case 7:
                $offset++;
                new FacturaCliente();
                break;
        }

        return true;
    }
}

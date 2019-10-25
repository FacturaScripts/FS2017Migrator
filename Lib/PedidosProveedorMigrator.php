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

/**
 * Description of PedidosProveedorMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class PedidosProveedorMigrator extends AlbaranesProveedorMigrator
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (0 === $offset && !$this->fixLinesTable('lineaspedidosprov')) {
            return false;
        }

        if (0 === $offset && !$this->fixSuppliers('pedidosprov')) {
            return false;
        }

        if (0 === $offset && !$this->setModelCompany('PedidoProveedor')) {
            return false;
        }

        $columns = $this->dataBase->getColumns('pedidosprov');
        if (!in_array('idalbaran', array_keys($columns))) {
            return true;
        }

        if (0 === $offset && !$this->setModelStatusAll('PedidoProveedor', 'idalbaran')) {
            return false;
        }

        return true;
    }
}

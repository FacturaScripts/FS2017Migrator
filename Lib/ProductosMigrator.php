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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Stock;

/**
 * Description of ProductosMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ProductosMigrator extends InicioMigrator
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    public function migrate(&$offset = 0)
    {
        /// rename stocks table
        if (!$this->dataBase->tableExists('stocks_old')) {
            $this->renameTable('stocks', 'stocks_old');
        }

        $sql = "SELECT * FROM articulos ORDER BY referencia ASC";
        $rows = $this->dataBase->selectLimit($sql, 100, $offset);
        foreach ($rows as $row) {
            if (!$this->newProduct($row)) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    /**
     * 
     * @param array $data
     *
     * @return bool
     */
    protected function newProduct($data)
    {
        $producto = new Producto();
        $where = [new DataBaseWhere('referencia', $data['referencia'])];
        if ($producto->loadFromCode('', $where)) {
            return true;
        }

        $producto->loadFromData($data);
        $producto->ventasinstock = Utils::str2bool($data['controlstock']);
        if ($producto->save()) {
            if (!$this->updateStock($producto)) {
                return false;
            }

            foreach ($producto->getVariants() as $variante) {
                $variante->codbarras = $data['codbarras'];
                $variante->coste = $data['costemedio'];
                $variante->precio = $data['pvp'];
                $variante->save();
            }

            return true;
        }

        return false;
    }

    /**
     * 
     * @param Producto $producto
     *
     * @return bool
     */
    protected function updateStock(&$producto)
    {
        $sql = "SELECT * FROM stocks_old WHERE referencia = '" . $producto->referencia . "';";
        foreach ($this->dataBase->select($sql) as $row) {
            $stock = new Stock($row);
            $stock->idproducto = $producto->idproducto;
            if (!$stock->save()) {
                return false;
            }
        }

        return true;
    }
}

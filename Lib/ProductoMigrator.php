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
 * Description of ProductoMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ProductoMigrator extends MigratorBase
{

    /**
     * 
     * @param int $offset
     *
     * @return int
     */
    public function migrate($offset = 0)
    {
        /// rename stocks table
        if (!$this->dataBase->tableExists('stocks_old')) {
            $this->renameTable('stocks', 'stocks_old');
        }

        $newOffset = 0;
        $sql = "SELECT * FROM articulos";
        foreach ($this->dataBase->selectLimit($sql, FS_ITEM_LIMIT, $offset) as $row) {
            $this->newProduct($row);
            $newOffset += empty($newOffset) ? 1 + $offset : 1;
        }

        return $newOffset;
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
            $this->updateStock($producto);
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
     */
    protected function updateStock(&$producto)
    {
        $sql = "SELECT * FROM stocks_old WHERE referencia = '" . $producto->referencia . "';";
        foreach ($this->dataBase->select($sql) as $row) {
            $stock = new Stock($row);
            $stock->idproducto = $producto->idproducto;
            $stock->save();
        }
    }
}

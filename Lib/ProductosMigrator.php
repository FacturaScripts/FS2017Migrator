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
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\Variante;

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
        return $this->migrateInTransaction($offset);
    }

    /**
     * 
     * @param string $ref
     * @param float  $precio
     *
     * @return array
     */
    protected function getCombinaciones($ref, $precio)
    {
        if (!$this->dataBase->tableExists('articulo_combinaciones')) {
            return [];
        }

        $combinaciones = [];
        $sql = "SELECT * FROM articulo_combinaciones WHERE referencia = " . $this->dataBase->var2str($ref) . " ORDER BY codigo ASC;";
        foreach ($this->dataBase->select($sql) as $row) {
            if (!isset($combinaciones[$row['codigo']])) {
                $combinaciones[$row['codigo']] = [
                    'codbarras' => $row['codbarras'],
                    'idatributovalor1' => $row['idvalor'],
                    'precio' => floatval($row['impactoprecio']) + $precio,
                    'referencia' => empty($row['refcombinacion']) ? $ref . '-' . $row['codigo'] : $row['refcombinacion'],
                    'stockfis' => floatval($row['stockfis'])
                ];
                continue;
            }

            $combinaciones[$row['codigo']]['idatributovalor2'] = $row['idvalor'];
        }

        return $combinaciones;
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
        $where = [new DataBaseWhere('referencia', trim($data['referencia']))];
        if ($producto->loadFromCode('', $where)) {
            return true;
        }

        $producto->loadFromData($data);
        $producto->ventasinstock = $this->toolBox()->utils()->str2bool($data['controlstock']);
        if ($producto->save()) {
            if ($data['tipo'] == 'atributos') {
                return $this->newProductVariants($producto, $data);
            }

            /// type: simple
            foreach ($producto->getVariants() as $variante) {
                $variante->codbarras = $data['codbarras'];
                $variante->coste = $data['costemedio'];
                $variante->precio = $data['pvp'];
                $variante->save();
                break;
            }

            if ($producto->stockfis != 0 && !$this->updateStock($producto)) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * 
     * @param Producto $producto
     * @param array    $data
     *
     * @return bool
     */
    protected function newProductVariants($producto, $data)
    {
        foreach ($this->getCombinaciones($producto->referencia, (float) $data['pvp']) as $combi) {
            $newVariante = new Variante($combi);
            $newVariante->idproducto = $producto->idproducto;
            if (!$newVariante->save()) {
                return false;
            }

            if ($newVariante->stockfis == 0) {
                continue;
            }

            $newStock = new Stock();
            $newStock->cantidad = $newVariante->stockfis;
            $newStock->codalmacen = $this->toolBox()->appSettings()->get('default', 'codalmacen');
            $newStock->idproducto = $newVariante->idproducto;
            $newStock->referencia = $newVariante->referencia;
            if (!$newStock->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function transactionProcess(&$offset = 0)
    {
        /// rename stocks table
        if ($offset == 0 && !$this->dataBase->tableExists('stocks_old')) {
            $this->renameTable('stocks', 'stocks_old');
        }

        $sql = "SELECT * FROM articulos ORDER BY referencia ASC";
        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
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
     * @param Producto $producto
     *
     * @return bool
     */
    protected function updateStock(&$producto)
    {
        $sql = "SELECT * FROM stocks_old WHERE referencia = " . $this->dataBase->var2str($producto->referencia) . ";";
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

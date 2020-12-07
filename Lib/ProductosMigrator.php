<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019-2020 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Dinamic\Model\AtributoValor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Description of ProductosMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ProductosMigrator extends MigratorBase
{

    /**
     *
     * @var array
     */
    private $attributeValues;

    /**
     * 
     * @param int $id
     *
     * @return int
     */
    private function checkAttributeValue($id)
    {
        if (null === $this->attributeValues) {
            $attValue = new AtributoValor();
            $this->attributeValues = $attValue->all([], [], 0, 0);
        }

        return \in_array($id, $this->attributeValues, true) ? $id : null;
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
        if (false === $this->dataBase->tableExists('articulo_combinaciones')) {
            return [];
        }

        $combinaciones = [];
        $sql = "SELECT * FROM articulo_combinaciones WHERE referencia = " . $this->dataBase->var2str($ref)
            . " ORDER BY codigo ASC;";
        foreach ($this->dataBase->select($sql) as $row) {
            if (!isset($combinaciones[$row['codigo']])) {
                $combinaciones[$row['codigo']] = [
                    'codbarras' => $this->fixString($row['codbarras'], 20),
                    'idatributovalor1' => $this->checkAttributeValue($row['idvalor']),
                    'precio' => \floatval($row['impactoprecio']) + $precio,
                    'referencia' => empty($row['refcombinacion']) ? $ref . '-' . $row['codigo'] : $row['refcombinacion'],
                    'stockfis' => \floatval($row['stockfis'])
                ];
                continue;
            }

            $combinaciones[$row['codigo']]['idatributovalor2'] = $this->checkAttributeValue($row['idvalor']);
        }

        return $combinaciones;
    }

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        /// rename stocks table
        if ($offset == 0 && false === $this->dataBase->tableExists('stocks_old')) {
            $this->renameTable('stocks', 'stocks_old');
        }
        if ($offset == 0 && false === $this->removeDuplicatedAttributeValues()) {
            return false;
        }
        if ($offset == 0 && false === $this->removeDuplicatedReferences()) {
            return false;
        }

        $sql = "SELECT * FROM articulos ORDER BY referencia ASC";
        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
        foreach ($rows as $row) {
            if (false === $this->newProduct($row)) {
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
        /// fix referencia
        if (\strlen($data['referencia']) > 30) {
            $data['referencia'] = \trim(\substr($data['referencia'], 0, 30));
        } elseif (empty($data['referencia'])) {
            $data['referencia'] = '-';
        }

        $producto = new Producto();
        $variante = new Variante();
        $where = [new DataBaseWhere('referencia', \trim($data['referencia']))];
        if ($producto->loadFromCode('', $where) || $variante->loadFromCode('', $where)) {
            return true;
        }

        $producto->loadFromData($data, ['stockfis']);
        $producto->ventasinstock = $this->toolBox()->utils()->str2bool($data['controlstock']);
        if (false === $producto->save()) {
            return false;
        }

        if ($data['tipo'] == 'atributos') {
            return $this->newProductVariants($producto, $data);
        }

        /// type: simple
        foreach ($producto->getVariants() as $vari) {
            $vari->codbarras = $this->fixString($data['codbarras'], 20);
            $vari->coste = $data['costemedio'];
            $vari->precio = $data['pvp'];
            $vari->save();
            break;
        }

        if (0 !== (int) $data['stockfis'] && false === $this->updateStock($producto)) {
            return false;
        }

        return true;
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
            $variante = new Variante();
            $where = [new DataBaseWhere('referencia', \trim($combi['referencia']))];
            if ($combi['referencia'] && $variante->loadFromCode('', $where)) {
                continue;
            }

            $newVariante = new Variante($combi);
            $newVariante->idproducto = $producto->idproducto;
            if (false === $newVariante->save()) {
                return false;
            }

            if ($newVariante->stockfis == 0) {
                continue;
            }

            $newStock = new Stock();
            $where[] = new DataBaseWhere('codalmacen', $this->toolBox()->appSettings()->get('default', 'codalmacen'));
            if (false === $newStock->loadFromCode('', $where)) {
                continue;
            }

            $newStock->cantidad = $newVariante->stockfis;
            $newStock->codalmacen = $this->toolBox()->appSettings()->get('default', 'codalmacen');
            $newStock->idproducto = $newVariante->idproducto;
            $newStock->referencia = $newVariante->referencia;
            if (false === $newStock->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * 
     * @return bool
     */
    private function removeDuplicatedAttributeValues(): bool
    {
        $sql = "SELECT codatributo, valor, COUNT(*) repeticiones FROM atributos_valores"
            . " GROUP BY codatributo, valor HAVING repeticiones > 1;";
        foreach ($this->dataBase->select($sql) as $row) {
            $sql2 = "SELECT * FROM atributos_valores"
                . " WHERE codatributo = " . $this->dataBase->var2str($row['codatributo'])
                . " AND valor = " . $this->dataBase->var2str($row['valor'])
                . " ORDER BY id DESC;";
            foreach ($this->dataBase->select($sql2) as $row2) {
                $sql3 = "DELETE FROM atributos_valores WHERE id = " . $this->dataBase->var2str($row2['id']) . ";";
                if (false === $this->dataBase->exec($sql3)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 
     * @return bool
     */
    private function removeDuplicatedReferences(): bool
    {
        if ($this->dataBase->tableExists('articulo_combinaciones')) {
            $sql = "DELETE FROM articulo_combinaciones WHERE refcombinacion IS NOT NULL"
                . " AND refcombinacion IN (SELECT referencia FROM articulos);";
            return $this->dataBase->exec($sql);
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
            if (false === $stock->save()) {
                return false;
            }
        }

        return true;
    }
}

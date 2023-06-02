<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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
    /** @var array */
    private $attributeValues;

    /**
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

        foreach ($this->attributeValues as $att) {
            if ($att->id == $id) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param string $ref
     * @param float $precio
     *
     * @return array
     */
    protected function getCombinaciones($ref, $precio): array
    {
        if (false === $this->dataBase->tableExists('articulo_combinaciones')) {
            return [];
        }

        $combinaciones = [];
        $sql = "SELECT * FROM articulo_combinaciones WHERE referencia = " . $this->dataBase->var2str($ref)
            . " ORDER BY codigo ASC;";
        foreach ($this->dataBase->select($sql) as $row) {
            $codigo = $row['codigo'];
            if (!isset($combinaciones[$codigo])) {
                $combinaciones[$codigo] = [
                    'codbarras' => $this->fixString($row['codbarras'], 20),
                    'idatributovalor1' => $this->checkAttributeValue($row['idvalor']),
                    'precio' => floatval($row['impactoprecio']) + $precio,
                    'referencia' => empty($row['refcombinacion']) ? $ref . '-' . $row['codigo'] : $row['refcombinacion'],
                    'stockfis' => floatval($row['stockfis'])
                ];
            } elseif (!isset($combinaciones[$codigo]['idatributovalor2'])) {
                $combinaciones[$codigo]['idatributovalor2'] = $this->checkAttributeValue($row['idvalor']);
            } elseif (!isset($combinaciones[$codigo]['idatributovalor3'])) {
                $combinaciones[$codigo]['idatributovalor3'] = $this->checkAttributeValue($row['idvalor']);
            } elseif (!isset($combinaciones[$codigo]['idatributovalor4'])) {
                $combinaciones[$codigo]['idatributovalor4'] = $this->checkAttributeValue($row['idvalor']);
            }
        }

        return $combinaciones;
    }

    private function fixFamilies(): bool
    {
        if (false === $this->dataBase->tableExists('familias')) {
            return true;
        }

        $sql = 'UPDATE articulos SET codfamilia = NULL WHERE codfamilia IS NOT NULL'
            . ' AND codfamilia NOT IN (SELECT codfamilia FROM familias)';
        return $this->dataBase->exec($sql);
    }

    private function fixManufacturers(): bool
    {
        if (false === $this->dataBase->tableExists('fabricantes')) {
            return true;
        }

        $sql = 'UPDATE articulos SET codfabricante = NULL WHERE codfabricante IS NOT NULL'
            . ' AND codfabricante NOT IN (SELECT codfabricante FROM fabricantes)';
        return $this->dataBase->exec($sql);
    }

    protected function migrationProcess(int &$offset = 0): bool
    {
        // rename stocks table
        if ($offset == 0 && false === $this->dataBase->tableExists('stocks_old')) {
            $this->renameTable('stocks', 'stocks_old');
        }
        if ($offset == 0 && false === $this->fixFamilies()) {
            return false;
        }
        if ($offset == 0 && false === $this->fixManufacturers()) {
            return false;
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

    protected function newProduct(array $data): bool
    {
        // fix referencia
        if (strlen($data['referencia']) > 30) {
            $data['referencia'] = trim(substr($data['referencia'], 0, 30));
        } elseif (empty(trim($data['referencia']))) {
            $data['referencia'] = '-';
        }

        $producto = new Producto();
        $variante = new Variante();
        $where = [new DataBaseWhere('referencia', trim($data['referencia']))];
        if ($producto->loadFromCode('', $where) || $variante->loadFromCode('', $where)) {
            return true;
        }

        $producto->loadFromData($data, ['controlstock', 'pvp', 'stockfis']);
        $producto->precio = (float)$data['pvp'];
        $producto->ventasinstock = $this->toolBox()->utils()->str2bool($data['controlstock']);
        $this->setSubcuentas($producto);

        if (false === $producto->save()) {
            return false;
        }

        if ($data['tipo'] == 'atributos') {
            return $this->newProductVariants($producto, $data);
        }

        // type: simple
        foreach ($producto->getVariants() as $vari) {
            $vari->codbarras = $this->fixString($data['codbarras'], 20);
            $vari->coste = $data['costemedio'];
            $vari->precio = $data['pvp'];
            $vari->save();
            break;
        }

        if (!empty($data['stockfis']) && false === $this->updateStock($producto, $data)) {
            return false;
        }

        return true;
    }

    protected function newProductVariants(Producto $producto, array $data): bool
    {
        foreach ($this->getCombinaciones($producto->referencia, (float)$data['pvp']) as $combi) {
            if (empty(trim($combi['referencia']))) {
                continue;
            }

            $variante = new Variante();
            $where = [new DataBaseWhere('referencia', trim($combi['referencia']))];
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

    private function removeDuplicatedReferences(): bool
    {
        if (false === $this->dataBase->tableExists('articulo_combinaciones')) {
            return true;
        }

        $data = $this->dataBase->select("SELECT * FROM articulo_combinaciones");
        if (empty($data)) {
            return true;
        }

        $sql = "DELETE FROM articulo_combinaciones WHERE refcombinacion IS NOT NULL"
            . " AND refcombinacion IN (SELECT referencia FROM articulos);";
        return $this->dataBase->exec($sql);
    }

    protected function setSubcuentas(Producto &$producto): void
    {
        if (false === $this->dataBase->tableExists('articulo_propiedades')) {
            return;
        }

        $sql = "SELECT * FROM articulo_propiedades WHERE referencia = " . $this->dataBase->var2str($producto->referencia);
        foreach ($this->dataBase->select($sql) as $row) {
            switch ($row['name']) {
                case 'codsubcuentaventa':
                    $producto->codsubcuentaven = $row['text'];
                    break;

                case 'codsubcuentacom':
                    $producto->codsubcuentacom = $row['text'];
                    break;

                case 'codsubcuentairpfcom':
                    $producto->codsubcuentairpfcom = $row['text'];
                    break;
            }
        }
    }

    protected function updateStock(Producto &$producto, array $data): bool
    {
        $sql = "SELECT * FROM stocks_old WHERE referencia = " . $this->dataBase->var2str($producto->referencia) . ";";
        foreach ($this->dataBase->select($sql) as $row) {
            $stock = new Stock($row);
            $stock->idproducto = $producto->idproducto;
            $stock->stockmax = $data['stockmax'];
            $stock->stockmin = $data['stockmin'];
            if (false === $stock->save()) {
                return false;
            }
        }

        return true;
    }
}

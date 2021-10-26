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

namespace FacturaScripts\Plugins\FS2017Migrator\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\DocTransformation;
use FacturaScripts\Dinamic\Model\EstadoDocumento;

/**
 * Description of AlbaranesProveedorMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class AlbaranesProveedorMigrator extends MigratorBase
{
    /**
     * @var array
     */
    private static $availableStatus = [];

    /**
     * @param string $tableName
     *
     * @return bool
     */
    protected function fixCustomers(string $tableName): bool
    {
        if (false === $this->dataBase->tableExists($tableName)) {
            return true;
        }

        $sql = "UPDATE " . $tableName . " SET codcliente = null WHERE codcliente IS NOT null"
            . " AND codcliente NOT IN (SELECT codcliente FROM clientes);"
            . "UPDATE " . $tableName . " SET codagente = null WHERE codagente IS NOT null"
            . " AND codagente NOT IN (SELECT codagente FROM agentes);";
        return $this->dataBase->exec($sql);
    }

    /**
     * @param string $tableName
     *
     * @return bool
     */
    protected function fixLinesTable(string $tableName): bool
    {
        if (false === $this->dataBase->tableExists($tableName)) {
            return true;
        }

        $values = [];
        foreach (\array_keys($this->impuestos) as $value) {
            $values[] = "'" . $value . "'";
        }

        $sql = "UPDATE " . $tableName . " SET recargo = 0 WHERE recargo IS null;"
            . " UPDATE " . $tableName . " SET codimpuesto = null WHERE codimpuesto IS NOT null"
            . " AND codimpuesto NOT IN (" . \implode(',', $values) . ");";
        return $this->dataBase->exec($sql);
    }

    /**
     * @param string $tableName
     *
     * @return bool
     */
    protected function fixSuppliers(string $tableName): bool
    {
        if (false === $this->dataBase->tableExists($tableName)) {
            return true;
        }

        $sql = "UPDATE " . $tableName . " SET codproveedor = null WHERE codproveedor IS NOT null"
            . " AND codproveedor NOT IN (SELECT codproveedor FROM proveedores);";
        return $this->dataBase->exec($sql);
    }

    /**
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (0 === $offset && false === $this->fixLinesTable('lineasalbaranesprov')) {
            return false;
        }

        if (0 === $offset && false === $this->fixSuppliers('albaranesprov')) {
            return false;
        }

        if (0 === $offset && false === $this->setModelCompany('AlbaranProveedor')) {
            return false;
        }

        if (0 === $offset && false === $this->setModelStatusAll('AlbaranProveedor', 'idfactura', true)) {
            return false;
        }

        $sql = "SELECT * FROM lineasalbaranesprov WHERE idpedido IS NOT null AND idpedido != '0' ORDER BY idlinea ASC";
        foreach ($this->dataBase->selectLimit($sql, 300, $offset) as $row) {
            $done = $this->newDocTransformation(
                'PedidoProveedor', $row['idpedido'], $row['idlineapedido'],
                'AlbaranProveedor', $row['idalbaran'], $row['idlinea']
            );
            if (false === $done) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    /**
     * @param int $model1
     * @param int $id1
     * @param int $idlinea1
     * @param int $model2
     * @param string $id2
     * @param string $idlinea2
     *
     * @return bool
     */
    protected function newDocTransformation($model1, $id1, $idlinea1, $model2, $id2, $idlinea2): bool
    {
        $docTransformation = new DocTransformation();
        $where = [
            new DataBaseWhere('iddoc1', $id1),
            new DataBaseWhere('iddoc2', $id2),
            new DataBaseWhere('idlinea2', $idlinea2),
            new DataBaseWhere('model1', $model1),
            new DataBaseWhere('model2', $model2)
        ];
        if ($docTransformation->loadFromCode('', $where)) {
            return true;
        }

        $docTransformation->iddoc1 = $id1;
        $docTransformation->iddoc2 = $id2;
        $docTransformation->idlinea1 = empty($idlinea1) ? 0 : $idlinea1;
        $docTransformation->idlinea2 = $idlinea2;
        $docTransformation->model1 = $model1;
        $docTransformation->model2 = $model2;
        if ($docTransformation->save()) {
            return $this->setModelStatus($model1, $id1, $model2);
        }

        return false;
    }

    /**
     * @param string $modelName
     *
     * @return bool
     */
    protected function setModelCompany(string $modelName): bool
    {
        $className = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName;
        $model1 = new $className();

        $codalmacen = $this->toolBox()->appSettings()->get('default', 'codalmacen');
        $idempresa = $this->toolBox()->appSettings()->get('default', 'idempresa');
        $sql = "UPDATE " . $model1->tableName() . " SET idempresa = " . $this->dataBase->var2str($idempresa)
            . " WHERE idempresa IS NULL;"
            . "UPDATE " . $model1->tableName() . " SET codalmacen = " . $this->dataBase->var2str($codalmacen)
            . " WHERE codalmacen IS NULL OR codalmacen NOT IN (SELECT codalmacen FROM almacenes);";
        return $this->dataBase->exec($sql);
    }

    /**
     * @param string $modelName1
     * @param int $id
     * @param string $modelName2
     *
     * @return bool
     */
    protected function setModelStatus($modelName1, $id, $modelName2): bool
    {
        if (empty(self::$availableStatus)) {
            $estadoDocModel = new EstadoDocumento();
            $where = [
                new DataBaseWhere('generadoc', $modelName2),
                new DataBaseWhere('tipodoc', $modelName1)
            ];
            self::$availableStatus = $estadoDocModel->all($where);
        }

        foreach (self::$availableStatus as $estado) {
            $className = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName1;
            $model1 = new $className();
            $sql = "UPDATE " . $model1->tableName() . " set idestado = '" . $estado->idestado
                . "' WHERE " . $model1->primaryColumn() . " = '" . $id . "';";

            return $this->dataBase->exec($sql);
        }

        return false;
    }

    /**
     * @param string $modelName
     * @param string $docNextColumn
     * @param bool $ptfactura
     *
     * @return bool
     */
    protected function setModelStatusAll($modelName, $docNextColumn = '', $ptfactura = false): bool
    {
        $className = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName;
        $model1 = new $className();

        // is ptefactura column present?
        if ($ptfactura && false === \in_array('ptefactura', \array_keys($model1->getModelFields()))) {
            return true;
        }

        $estadoDocModel = new EstadoDocumento();
        $where = [new DataBaseWhere('tipodoc', $modelName)];
        foreach ($estadoDocModel->all($where) as $estado) {
            $sql = "UPDATE " . $model1->tableName() . " SET idestado = " . $this->dataBase->var2str($estado->idestado);

            if ($ptfactura) {
                $sql .= " WHERE idestado IS NULL AND ptefactura = " . $this->dataBase->var2str($estado->editable);
            } else {
                $sql .= " WHERE idestado IS NULL AND editable = " . $this->dataBase->var2str($estado->editable);
            }

            if (!empty($docNextColumn)) {
                $sql .= empty($estado->generadoc) ? " AND " . $docNextColumn . " IS null;" : " AND " . $docNextColumn . " IS NOT null;";
            }

            if (false === $this->dataBase->exec($sql)) {
                return false;
            }

            // update lines
            $sql2 = "UPDATE " . $model1->getNewLine()->tableName() . " SET actualizastock = " . $this->dataBase->var2str($estado->actualizastock)
                . " WHERE " . $model1->primaryColumn() . " IN (SELECT " . $model1->primaryColumn()
                . " FROM " . $model1->tableName() . " WHERE idestado = " . $this->dataBase->var2str($estado->idestado) . ")";
            if (false === $this->dataBase->exec($sql2)) {
                return false;
            }
        }

        return true;
    }
}

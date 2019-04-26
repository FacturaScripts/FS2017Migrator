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
use FacturaScripts\Dinamic\Model\DocTransformation;
use FacturaScripts\Dinamic\Model\EstadoDocumento;

/**
 * Description of AlbaranesProveedorMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class AlbaranesProveedorMigrator extends InicioMigrator
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
     * @param string $tableName
     *
     * @return bool
     */
    protected function fixLinesTable($tableName)
    {
        if (!$this->dataBase->tableExists($tableName)) {
            return true;
        }

        $values = [];
        foreach (array_keys($this->impuestos) as $value) {
            $values[] = "'" . $value . "'";
        }

        $sql = "UPDATE " . $tableName . " SET recargo = 0 WHERE recargo IS null;"
            . " UPDATE " . $tableName . " SET codimpuesto = null WHERE codimpuesto IS NOT null"
            . " AND codimpuesto NOT IN (" . implode(',', $values) . ");";
        return $this->dataBase->exec($sql);
    }

    /**
     * 
     * @param int    $model1
     * @param int    $id1
     * @param int    $idlinea1
     * @param int    $model2
     * @param string $id2
     * @param string $idlinea2
     *
     * @return bool
     */
    protected function newDocTransformation($model1, $id1, $idlinea1, $model2, $id2, $idlinea2)
    {
        $docTransformation = new DocTransformation();
        $where = [
            new DataBaseWhere('iddoc1', $id1),
            new DataBaseWhere('iddoc2', $id2),
            new DataBaseWhere('idlinea2', $idlinea2),
            new DataBaseWhere('model1', $model1),
            new DataBaseWhere('model2', $model2),
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
     * 
     * @param string $modelName
     *
     * @return bool
     */
    protected function setModelCompany($modelName)
    {
        $className = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName;
        $model1 = new $className();

        $idempresa = AppSettings::get('default', 'idempresa');
        $sql = "UPDATE " . $model1->tableName() . " set idempresa = " . $this->dataBase->var2str($idempresa);
        return $this->dataBase->exec($sql);
    }

    /**
     * 
     * @param string $modelName1
     * @param int    $id
     * @param string $modelName2
     *
     * @return bool
     */
    protected function setModelStatus($modelName1, $id, $modelName2)
    {
        $estadoDocModel = new EstadoDocumento();
        $where = [
            new DataBaseWhere('generadoc', $modelName2),
            new DataBaseWhere('tipodoc', $modelName1),
        ];
        foreach ($estadoDocModel->all($where) as $estado) {
            $className = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName1;
            $model1 = new $className();
            $sql = "UPDATE " . $model1->tableName() . " set idestado = '" . $estado->idestado
                . "' WHERE " . $model1->primaryColumn() . " = '" . $id . "';";

            return $this->dataBase->exec($sql);
        }

        return false;
    }

    /**
     * 
     * @param string $modelName
     * @param string $docNextColumn
     *
     * @return bool
     */
    protected function setModelStatusAll($modelName, $docNextColumn = '')
    {
        $className = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName;
        $model1 = new $className();

        $estadoDocModel = new EstadoDocumento();
        $where = [new DataBaseWhere('tipodoc', $modelName)];
        foreach ($estadoDocModel->all($where) as $estado) {
            $sql = "UPDATE " . $model1->tableName() . " set idestado = '" . $estado->idestado
                . "' WHERE editable = " . $this->dataBase->var2str($estado->editable);

            if (!empty($docNextColumn)) {
                $sql .= empty($estado->generadoc) ? " AND " . $docNextColumn . " IS null;" : " AND " . $docNextColumn . " IS NOT null;";
            }

            if (!$this->dataBase->exec($sql)) {
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
        if (0 === $offset && !$this->fixLinesTable('lineasalbaranesprov')) {
            return false;
        }

        if (0 === $offset && !$this->setModelCompany('AlbaranProveedor')) {
            return false;
        }

        if (0 === $offset && !$this->setModelStatusAll('AlbaranProveedor', 'idfactura')) {
            return false;
        }

        $sql = "SELECT * FROM lineasalbaranesprov"
            . " WHERE idpedido IS NOT null"
            . " AND idpedido != '0'"
            . " ORDER BY idlinea ASC";

        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
        foreach ($rows as $row) {
            $done = $this->newDocTransformation(
                'PedidoProveedor', $row['idpedido'], $row['idlineapedido'], 'AlbaranProveedor', $row['idalbaran'], $row['idlinea']
            );
            if (!$done) {
                return false;
            }

            $offset++;
        }

        return true;
    }
}

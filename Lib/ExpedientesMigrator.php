<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2021-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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
 * Description of ExpedientesMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ExpedientesMigrator extends MigratorBase
{

    /**
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (false === $this->dataBase->tableExists('expedientes') ||
            false === class_exists('\FacturaScripts\Dinamic\Model\Proyecto')) {
            return true;
        }

        $sql = 'SELECT * FROM expedientes ORDER BY id ASC';
        foreach ($this->dataBase->selectLimit($sql, 300, $offset) as $row) {
            if (false === $this->newProyecto($row)) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    private function linkDocument(int $id, string $tableName, string $colName, ?int $iddoc)
    {
        if (empty($iddoc)) {
            return;
        }

        $sql = 'UPDATE ' . $tableName . ' SET idproyecto = ' . $this->dataBase->var2str($id)
            . ' WHERE ' . $colName . ' = ' . $this->dataBase->var2str($iddoc);
        $this->dataBase->exec($sql);
    }

    private function linkDocuments(int $id): bool
    {
        $sql = 'SELECT * FROM expediente_documentos WHERE id_expediente = ' . $this->dataBase->var2str($id);
        foreach ($this->dataBase->select($sql) as $row) {
            $this->linkDocument($id, 'albaranescli', 'idalbaran', $row['id_albaran_ventas']);
            $this->linkDocument($id, 'albaranesprov', 'idalbaran', $row['id_albaran_compras']);
            $this->linkDocument($id, 'facturascli', 'idfactura', $row['id_factura_ventas']);
            $this->linkDocument($id, 'facturasprov', 'idfactura', $row['id_factura_compras']);
            $this->linkDocument($id, 'pedidoscli', 'idpedido', $row['id_pedido_ventas']);
            $this->linkDocument($id, 'pedidosprov', 'idpedido', $row['id_pedido_compras']);
            $this->linkDocument($id, 'presupuestoscli', 'idpresupuesto', $row['id_presupuesto_ventas']);
        }

        return true;
    }

    private function newProyecto(array $row): bool
    {
        $proyecto = new \FacturaScripts\Plugins\Proyectos\Model\Proyecto();
        if ($proyecto->loadFromCode($row['id'])) {
            return $this->linkDocuments($row['id']);
        }

        $proyecto->fecha = date($proyecto::DATE_STYLE, strtotime($row['fecha']));
        $proyecto->fechafin = empty($row['fecha_fin']) ? null : date($proyecto::DATE_STYLE, strtotime($row['fecha_fin']));
        $proyecto->fechainicio = empty($row['fecha_inicio']) ? null : date($proyecto::DATE_STYLE, strtotime($row['fecha_inicio']));
        $proyecto->idempresa = $this->toolBox()->appSettings()->get('default', 'idempresa');
        $proyecto->idproyecto = (int)$row['id'];
        if ($this->toolBox()->utils()->str2bool($row['finalizado'])) {
            $proyecto->editable = true;
            $proyecto->idestado = 3;
        }

        // elegimos codigo, numero2 o nombre como identificador, en función de cual esté rellenado
        if ($row['codigo']) {
            $proyecto->nombre = $row['codigo'];
            $proyecto->descripcion = trim($row['nombre'] . "\n" . $row['descripcion'] . "\n" . $row['numero2'] . "\n" . $row['observaciones']);
        } elseif ($row['numero2']) {
            $proyecto->nombre = $row['numero2'];
            $proyecto->descripcion = trim($row['nombre'] . "\n" . $row['descripcion'] . "\n" . $row['codigo'] . "\n" . $row['observaciones']);
        } else {
            $proyecto->nombre = substr($row['nombre'], 0, 100);
            $proyecto->descripcion = trim($row['descripcion'] . "\n" . $row['numero2'] . "\n" . $row['observaciones']);
        }

        return $proyecto->save() && $this->linkDocuments($proyecto->idproyecto);
    }
}

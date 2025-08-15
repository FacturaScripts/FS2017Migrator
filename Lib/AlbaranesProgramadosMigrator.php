<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2021-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\DocRecurringSale;

/**
 * Description of AlbaranesProgramadosMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class AlbaranesProgramadosMigrator extends MigratorBase
{
    protected function migrationProcess(int &$offset = 0): bool
    {
        if (false === $this->dataBase->tableExists('albaranescli_prog') ||
            false === class_exists('\FacturaScripts\Dinamic\Model\DocRecurringSale')) {
            return true;
        }

        $sql = 'SELECT * FROM albaranescli_prog ORDER BY id ASC';
        foreach ($this->dataBase->selectLimit($sql, 300, $offset) as $row) {
            if (false === $this->newDocRecurringSale($row)) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    protected function getLastData(int $idalbaran): ?string
    {
        $albaran = new AlbaranCliente();
        return $albaran->load($idalbaran) ? $albaran->fecha : null;
    }

    protected function newDocRecurringSale(array $row): bool
    {
        $docRecurring = new DocRecurringSale();
        if ($docRecurring->loadFromCode($row['id'])) {
            return true;
        }

        if ($row['fechafin'] && strtotime($row['fechafin']) < time()) {
            return true;
        }

        $customer = new Cliente();
        if (false === $customer->load($row['codcliente'])) {
            return true;
        }

        $albaran = new AlbaranCliente();
        if (false === $albaran->load($row['idalbaran'])) {
            return true;
        }

        // new recurring document
        $docRecurring->codagente = $albaran->codagente;
        $docRecurring->codalmacen = $albaran->codalmacen;
        $docRecurring->codcliente = $customer->codcliente;
        $docRecurring->coddivisa = $albaran->coddivisa;
        $docRecurring->codpago = $albaran->codpago;
        $docRecurring->codserie = $albaran->codserie;
        $docRecurring->enddate = $row['fechafin'];
        $docRecurring->generatedoc = $this->str2bool($row['facturar']) ? 'FacturaCliente' : 'AlbaranCliente';
        $docRecurring->id = $row['id'];
        $docRecurring->name = $row['concepto'];

        switch ($row['periodo']) {
            case 'a':
                $docRecurring->termtype = DocRecurringSale::TERM_TYPE_MONTHS;
                $docRecurring->termunits = 12;
                break;

            case 'd':
                $docRecurring->termtype = DocRecurringSale::TERM_TYPE_DAYS;
                $docRecurring->termunits = $row['repeticion'];
                break;

            default:
                $docRecurring->termtype = DocRecurringSale::TERM_TYPE_MONTHS;
                $docRecurring->termunits = $row['repeticion'];
                break;
        }

        $lastDate = $this->getLastData($row['ultimo_idalbaran']);
        if ($lastDate) {
            $docRecurring->startdate = Tools::date($lastDate);
        } else {
            $day = date('d', strtotime($row['fecha']));
            $docRecurring->startdate = date($day . '-m-Y', strtotime($row['fecha']));
        }

        return $docRecurring->save() && $this->newDocRecurringSaleLines($docRecurring, $albaran, $row);
    }

    /**
     * @param DocRecurringSale $docRecurring
     * @param AlbaranCliente $albaran
     * @param array $row
     *
     * @return bool
     */
    protected function newDocRecurringSaleLines($docRecurring, $albaran, $row): bool
    {
        foreach ($albaran->getLines() as $line) {
            $newLine = new DocRecurringSaleLine();
            $newLine->discount = $line->dtopor;
            $newLine->iddoc = $docRecurring->id;
            $newLine->name = empty($line->descripcion) ? '-' : $line->descripcion;
            $newLine->quantity = $line->cantidad;

            $product = $line->getProducto();
            if ($product) {
                $newLine->price = $this->str2bool($row['actualizar_precios']) ? 0 : $line->pvpunitario;
                $newLine->reference = $line->referencia;
            } else {
                $newLine->price = $line->pvpunitario;
            }

            if (false === $newLine->save()) {
                return false;
            }
        }

        return true;
    }
}

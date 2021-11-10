<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2021 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\Cliente;

/**
 * Description of AlbaranesProgramadosMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class AlbaranesProgramadosMigrator extends MigratorBase
{

    /**
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
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

    /**
     * @param int $idalbaran
     *
     * @return string
     */
    protected function getLastData($idalbaran)
    {
        $albaran = new AlbaranCliente();
        return $albaran->loadFromCode($idalbaran) ? $albaran->fecha : null;
    }

    /**
     * @param array $row
     *
     * @return bool
     */
    protected function newDocRecurringSale($row): bool
    {
        $docRecurring = new \FacturaScripts\Plugins\DocumentosRecurrentes\Model\DocRecurringSale();
        if ($docRecurring->loadFromCode($row['id'])) {
            return true;
        }

        if ($row['fechafin'] && strtotime($row['fechafin']) < \time()) {
            return true;
        }

        $customer = new Cliente();
        if (false === $customer->loadFromCode($row['codcliente'])) {
            return true;
        }

        $albaran = new AlbaranCliente();
        if (false === $albaran->loadFromCode($row['idalbaran'])) {
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
        $docRecurring->generatedoc = $this->toolBox()->utils()->str2bool($row['facturar']) ? 'FacturaCliente' : 'AlbaranCliente';
        $docRecurring->id = $row['id'];
        $docRecurring->name = $row['concepto'];

        switch ($row['periodo']) {
            case 'a':
                $docRecurring->termtype = \FacturaScripts\Plugins\DocumentosRecurrentes\Model\DocRecurringSale::TERM_TYPE_MONTHS;
                $docRecurring->termunits = 12;
                break;

            case 'd':
                $docRecurring->termtype = \FacturaScripts\Plugins\DocumentosRecurrentes\Model\DocRecurringSale::TERM_TYPE_DAYS;
                $docRecurring->termunits = $row['repeticion'];
                break;

            default:
                $docRecurring->termtype = \FacturaScripts\Plugins\DocumentosRecurrentes\Model\DocRecurringSale::TERM_TYPE_MONTHS;
                $docRecurring->termunits = $row['repeticion'];
                break;
        }

        $lastdate = $this->getLastData($row['ultimo_idalbaran']);
        if ($lastdate) {
            $docRecurring->startdate = date(AlbaranCliente::DATE_STYLE, strtotime($lastdate));
        } else {
            $day = date('d', strtotime($row['fecha']));
            $docRecurring->startdate = date($day . '-m-Y', strtotime($row['fecha']));
        }

        return $docRecurring->save() && $this->newDocRecurringSaleLines($docRecurring, $albaran, $row);
    }

    /**
     * @param \FacturaScripts\Plugins\DocumentosRecurrentes\Model\DocRecurringSale $docRecurring
     * @param AlbaranCliente $albaran
     * @param array $row
     *
     * @return bool
     */
    protected function newDocRecurringSaleLines($docRecurring, $albaran, $row): bool
    {
        foreach ($albaran->getLines() as $line) {
            $newLine = new \FacturaScripts\Plugins\DocumentosRecurrentes\Model\DocRecurringSaleLine();
            $newLine->discount = $line->dtopor;
            $newLine->iddoc = $docRecurring->id;
            $newLine->name = empty($line->descripcion) ? '-' : $line->descripcion;
            $newLine->quantity = $line->cantidad;

            $product = $line->getProducto();
            if ($product) {
                $newLine->price = $this->toolBox()->utils()->str2bool($row['actualizar_precios']) ? 0 : $line->pvpunitario;
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

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
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\SecuenciaDocumento;
use FacturaScripts\Dinamic\Model\Serie;

/**
 * Description of SecuenciasMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class SecuenciasMigrator extends MigratorBase
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        /// create sequences for every serie
        $serieModel = new Serie();
        foreach ($serieModel->all() as $serie) {
            $this->invoices($serie->codserie);
            $this->deliveryNotes($serie->codserie);
            $this->orders($serie->codserie);
            $this->estimations($serie->codserie);
        }

        return true;
    }

    /**
     * 
     * @param string $codserie
     * @param string $tipodoc
     */
    protected function deliveryNotes(string $codserie, string $tipodoc = 'AlbaranCliente')
    {
        /// sequence exists for this serie?
        $secuencia = new SecuenciaDocumento();
        $where = [
            new DataBaseWhere('codserie', $codserie),
            new DataBaseWhere('tipodoc', $tipodoc)
        ];
        if ($secuencia->loadFromCode('', $where)) {
            return;
        }

        /// find delivery notes
        $albaranClienteModel = new AlbaranCliente();
        $where2 = [new DataBaseWhere('codserie', $codserie)];
        $order = ['numero' => 'DESC'];
        foreach ($albaranClienteModel->all($where2, $order, 0, 1) as $albaran) {
            if (\substr($albaran->codigo, 0, 3) == 'ALB') {
                $secuencia->codejercicio = $albaran->codejercicio;
                $secuencia->codserie = $albaran->codserie;
                $secuencia->idempresa = $albaran->idempresa;
                $secuencia->numero = 1 + $albaran->numero;
                $secuencia->patron = 'ALB{EJE}{SERIE}{NUM}';
                $secuencia->tipodoc = $tipodoc;
                $secuencia->usarhuecos = false;
                $secuencia->save();
            }

            break;
        }
    }

    /**
     * 
     * @param string $codserie
     * @param string $tipodoc
     */
    protected function estimations(string $codserie, string $tipodoc = 'PresupuestoCliente')
    {
        /// sequence exists for this serie?
        $secuencia = new SecuenciaDocumento();
        $where = [
            new DataBaseWhere('codserie', $codserie),
            new DataBaseWhere('tipodoc', $tipodoc)
        ];
        if ($secuencia->loadFromCode('', $where)) {
            return;
        }

        /// find delivery notes
        $presupuestoClienteModel = new PresupuestoCliente();
        $where2 = [new DataBaseWhere('codserie', $codserie)];
        $order = ['numero' => 'DESC'];
        foreach ($presupuestoClienteModel->all($where2, $order, 0, 1) as $presupuesto) {
            if (\substr($presupuesto->codigo, 0, 3) == 'PRE') {
                $secuencia->codejercicio = $presupuesto->codejercicio;
                $secuencia->codserie = $presupuesto->codserie;
                $secuencia->idempresa = $presupuesto->idempresa;
                $secuencia->numero = 1 + $presupuesto->numero;
                $secuencia->patron = 'PRE{EJE}{SERIE}{NUM}';
                $secuencia->tipodoc = $tipodoc;
                $secuencia->usarhuecos = false;
                $secuencia->save();
            }

            break;
        }
    }

    /**
     * 
     * @param string $codserie
     * @param string $tipodoc
     */
    protected function invoices(string $codserie, string $tipodoc = 'FacturaCliente')
    {
        /// sequence exists for this serie?
        $secuencia = new SecuenciaDocumento();
        $where = [
            new DataBaseWhere('codserie', $codserie),
            new DataBaseWhere('tipodoc', $tipodoc)
        ];
        if ($secuencia->loadFromCode('', $where)) {
            return;
        }

        /// find invoices
        $facturaClienteModel = new FacturaCliente();
        $where2 = [new DataBaseWhere('codserie', $codserie)];
        $order = ['numero' => 'DESC'];
        foreach ($facturaClienteModel->all($where2, $order, 0, 1) as $factura) {
            if (\substr($factura->codigo, 0, 3) == 'FAC') {
                $secuencia->codejercicio = $factura->codejercicio;
                $secuencia->codserie = $factura->codserie;
                $secuencia->idempresa = $factura->idempresa;
                $secuencia->numero = 1 + $factura->numero;
                $secuencia->patron = 'FAC{EJE}{SERIE}{NUM}';
                $secuencia->tipodoc = $tipodoc;
                $secuencia->usarhuecos = true;
                $secuencia->save();
            }

            break;
        }
    }

    /**
     * 
     * @param string $codserie
     * @param string $tipodoc
     */
    protected function orders(string $codserie, string $tipodoc = 'PedidoCliente')
    {
        /// sequence exists for this serie?
        $secuencia = new SecuenciaDocumento();
        $where = [
            new DataBaseWhere('codserie', $codserie),
            new DataBaseWhere('tipodoc', $tipodoc)
        ];
        if ($secuencia->loadFromCode('', $where)) {
            return;
        }

        /// find delivery notes
        $pedidoClienteModel = new PedidoCliente();
        $where2 = [new DataBaseWhere('codserie', $codserie)];
        $order = ['numero' => 'DESC'];
        foreach ($pedidoClienteModel->all($where2, $order, 0, 1) as $pedido) {
            if (\substr($pedido->codigo, 0, 3) == 'PED') {
                $secuencia->codejercicio = $pedido->codejercicio;
                $secuencia->codserie = $pedido->codserie;
                $secuencia->idempresa = $pedido->idempresa;
                $secuencia->numero = 1 + $pedido->numero;
                $secuencia->patron = 'PED{EJE}{SERIE}{NUM}';
                $secuencia->tipodoc = $tipodoc;
                $secuencia->usarhuecos = false;
                $secuencia->save();
            }

            break;
        }
    }
}

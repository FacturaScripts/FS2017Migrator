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
use FacturaScripts\Dinamic\Model\FacturaCliente;
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
            /// sequence exists for this serie?
            $secuencia = new SecuenciaDocumento();
            $where = [
                new DataBaseWhere('codserie', $serie->codserie),
                new DataBaseWhere('tipodoc', 'FacturaCliente')
            ];
            if ($secuencia->loadFromCode('', $where)) {
                continue;
            }

            /// find invoices
            $facturaClienteModel = new FacturaCliente();
            $where2 = [new DataBaseWhere('codserie', $serie->codserie)];
            $order = ['fecha' => 'DESC', 'numero' => 'ASC'];
            foreach ($facturaClienteModel->all($where2, $order) as $factura) {
                if (\substr($factura->codigo, 0, 3) == 'FAC') {
                    $secuencia->codejercicio = $factura->codejercicio;
                    $secuencia->codserie = $factura->codserie;
                    $secuencia->idempresa = $factura->idempresa;
                    $secuencia->numero = 1 + $factura->numero;
                    $secuencia->patron = 'FAC{EJE}{SERIE}{NUM}';
                    $secuencia->tipodoc = 'FacturaCliente';
                    $secuencia->usarhuecos = true;
                    $secuencia->save();
                }

                break;
            }
        }

        return true;
    }
}

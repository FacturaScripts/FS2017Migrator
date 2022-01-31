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
use FacturaScripts\Dinamic\Model\AlbaranProveedor;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Dinamic\Model\PedidoProveedor;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\PresupuestoProveedor;
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
        // create sequences for every serie
        $serieModel = new Serie();
        foreach ($serieModel->all() as $serie) {
            $this->migrateSequence($serie->codserie, new FacturaCliente(), true);
            $this->migrateSequence($serie->codserie, new FacturaProveedor(), false);
            $this->migrateSequence($serie->codserie, new AlbaranCliente(), false);
            $this->migrateSequence($serie->codserie, new AlbaranProveedor(), false);
            $this->migrateSequence($serie->codserie, new PedidoCliente(), false);
            $this->migrateSequence($serie->codserie, new PedidoProveedor(), false);
            $this->migrateSequence($serie->codserie, new PresupuestoCliente(), false);
            $this->migrateSequence($serie->codserie, new PresupuestoProveedor(), false);
        }

        return true;
    }

    /**
     * 
     * @param string         $codserie
     * @param FacturaCliente $model
     * @param bool           $usarhuecos
     */
    protected function migrateSequence(string $codserie, $model, bool $usarhuecos)
    {
        $tipodoc = $model->modelClassName();

        // sequence exists for this serie?
        $secuencia = new SecuenciaDocumento();
        $where = [
            new DataBaseWhere('codserie', $codserie),
            new DataBaseWhere('tipodoc', $tipodoc)
        ];
        if ($secuencia->loadFromCode('', $where)) {
            return;
        }

        // find previous data
        $where2 = [new DataBaseWhere('codserie', $codserie)];
        $order = \strtolower(\FS_DB_TYPE) == 'postgresql' ? ['CAST(numero as integer)' => 'DESC'] : ['CAST(numero as unsigned)' => 'DESC'];
        foreach ($model->all($where2, $order, 0, 1) as $doc) {
            $prefix = \substr(\strtoupper($tipodoc), 0, 3);
            if (\substr($doc->codigo, 0, 3) == $prefix) {
                $secuencia->codejercicio = $doc->codejercicio;
                $secuencia->codserie = $doc->codserie;
                $secuencia->idempresa = $doc->idempresa;
                $secuencia->numero = 1 + $doc->numero;
                $secuencia->patron = $prefix . '{EJE}{SERIE}{NUM}';
                $secuencia->tipodoc = $tipodoc;
                $secuencia->usarhuecos = $usarhuecos;
                $secuencia->save();
            }

            break;
        }
    }
}

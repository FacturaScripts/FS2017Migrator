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
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\PagoProveedor;
use FacturaScripts\Dinamic\Model\ReciboProveedor;

/**
 * Description of PagosProveedorMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class PagosProveedorMigrator extends MigratorBase
{

    /**
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        $sql = 'SELECT * FROM facturasprov WHERE pagada = TRUE OR idasientop IS NOT NULL ORDER BY idfactura ASC';
        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
        foreach ($rows as $row) {
            if (false === $this->newReceipt($row)) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    /**
     * @param ReciboProveedor $receipt
     * @param string $idasientop
     *
     * @return bool
     */
    protected function newPayment($receipt, $idasientop): bool
    {
        $newPayment = new PagoProveedor();
        $newPayment->codpago = $receipt->codpago;
        $newPayment->fecha = $receipt->fecha;
        $newPayment->idrecibo = $receipt->idrecibo;
        $newPayment->importe = $receipt->importe;

        $asiento = new Asiento();
        if ($idasientop && $asiento->loadFromCode($idasientop)) {
            $newPayment->idasiento = $idasientop;
        }

        return $newPayment->save();
    }

    /**
     * @param array $row
     *
     * @return bool
     */
    protected function newReceipt(array $row): bool
    {
        $newReceipt = new ReciboProveedor();
        $where = [new DataBaseWhere('idfactura', $row['idfactura'])];
        if ($newReceipt->loadFromCode('', $where) || empty($row['codproveedor'])) {
            return true;
        }

        $newReceipt->disablePaymentGeneration();
        $newReceipt->codproveedor = $row['codproveedor'];
        $newReceipt->coddivisa = $row['coddivisa'];
        $newReceipt->codpago = $row['codpago'];
        $newReceipt->fecha = date('d-m-Y', strtotime($row['fecha']));
        $newReceipt->fechapago = date('d-m-Y', strtotime($row['fecha']));
        $newReceipt->idfactura = $row['idfactura'];
        $newReceipt->importe = $row['total'];
        $newReceipt->pagado = $this->toolBox()->utils()->str2bool($row['pagada']);
        $newReceipt->vencimiento = date('d-m-Y', strtotime($row['fecha']));
        return $newReceipt->save() && $this->newPayment($newReceipt, $row['idasientop']);
    }
}

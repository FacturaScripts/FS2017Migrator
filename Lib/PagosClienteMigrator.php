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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Dinamic\Model\PagoCliente;
use FacturaScripts\Dinamic\Model\ReciboCliente;

/**
 * Description of PagosClienteMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class PagosClienteMigrator extends InicioMigrator
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
     * @param ReciboCliente $receipt
     * @param string        $idasientop
     *
     * @return bool
     */
    protected function newPayment($receipt, $idasientop)
    {
        $newPayment = new PagoCliente();
        $newPayment->codpago = $receipt->codpago;
        $newPayment->fecha = $receipt->fechapago;
        $newPayment->idasiento = $idasientop;
        $newPayment->idrecibo = $receipt->idrecibo;
        $newPayment->importe = $receipt->importe;
        if (!$newPayment->save()) {
            return false;
        }

        return true;
    }

    /**
     * 
     * @param array $row
     *
     * @return bool
     */
    protected function newReceipt($row)
    {
        $newReceipt = new ReciboCliente();
        $where = [new DataBaseWhere('idfactura', $row['idfactura'])];
        if ($newReceipt->loadFromCode('', $where)) {
            return true;
        }

        $newReceipt->disablePaymentGeneration();
        $newReceipt->codcliente = $row['codcliente'];
        $newReceipt->coddivisa = $row['coddivisa'];
        $newReceipt->codpago = $row['codpago'];
        $newReceipt->fecha = date('d-m-Y', strtotime($row['fecha']));
        $newReceipt->fechapago = date('d-m-Y', strtotime($row['fecha']));
        $newReceipt->idfactura = $row['idfactura'];
        $newReceipt->importe = $row['total'];
        $newReceipt->pagado = Utils::str2bool($row['pagada']);
        $newReceipt->vencimiento = date('d-m-Y', strtotime($row['vencimiento']));
        return $newReceipt->save() ? $this->newPayment($newReceipt, $row['idasientop']) : false;
    }

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function transactionProcess(&$offset = 0)
    {
        $sql = 'SELECT * FROM facturascli WHERE idasientop IS NOT NULL ORDER BY idfactura ASC';

        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
        foreach ($rows as $row) {
            $done = $this->newReceipt($row);
            if (!$done) {
                return false;
            }

            $offset++;
        }

        return true;
    }
}

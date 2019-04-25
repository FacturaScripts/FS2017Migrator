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

use FacturaScripts\Dinamic\Model\Almacen;
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FormaPago;

/**
 * Description of EmpresaMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EmpresaMigrator extends InicioMigrator
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    public function migrate(&$offset = 0)
    {
        $sql = "SELECT * FROM empresa;";
        foreach ($this->dataBase->select($sql) as $row) {
            $this->updateCompany($row);
        }

        return true;
    }

    /**
     * 
     * @param int $idempresa
     */
    protected function updateAccounting($idempresa)
    {
        $ejercicioModel = new Ejercicio();
        foreach ($ejercicioModel->all([], [], 0, 0) as $ejercicio) {
            $ejercicio->idempresa = $idempresa;
            $ejercicio->save();
        }
    }

    /**
     * 
     * @param int $idempresa
     */
    protected function updateBankAccounts($idempresa)
    {
        $cuentaBancoModel = new CuentaBanco();
        foreach ($cuentaBancoModel->all([], [], 0, 0) as $cuentaBanco) {
            $cuentaBanco->idempresa = $idempresa;
            $cuentaBanco->save();
        }
    }

    /**
     * Updates company data with previous data.
     * 
     * @param array $data
     */
    protected function updateCompany($data)
    {
        $exclude = ['regimeniva'];

        $empresaModel = new Empresa();
        foreach ($empresaModel->all() as $empresa) {
            foreach ($data as $key => $value) {
                if (!in_array($key, $exclude)) {
                    $empresa->{$key} = $value;
                }
            }

            if (!$empresa->save()) {
                $this->miniLog->warning($this->i18n->trans('record-save-error'));
                return;
            }

            $this->appSettings->set('default', 'idempresa', $empresa->idempresa);
            $this->updateWarehouses($empresa->idempresa, $data['codalmacen']);
            $this->updatePaymentMethods($empresa->idempresa, $data['codpago']);
            $this->updateBankAccounts($empresa->idempresa);
            $this->updateAccounting($empresa->idempresa);
            $this->appSettings->save();
            break;
        }
    }

    /**
     * 
     * @param int    $idempresa
     * @param string $codalmacen
     */
    protected function updatePaymentMethods($idempresa, $codpago)
    {
        $formaPagoModel = new FormaPago();
        foreach ($formaPagoModel->all() as $formaPago) {
            $formaPago->idempresa = $idempresa;
            $formaPago->save();

            if ($formaPago->codpago == $codpago) {
                $this->appSettings->set('default', 'codpago', $codpago);
            }
        }
    }

    /**
     * 
     * @param int    $idempresa
     * @param string $codalmacen
     */
    protected function updateWarehouses($idempresa, $codalmacen)
    {
        $almacenModel = new Almacen();
        foreach ($almacenModel->all() as $almacen) {
            $almacen->idempresa = $idempresa;
            $almacen->save();

            if ($almacen->codalmacen == $codalmacen) {
                $this->appSettings->set('default', 'codalmacen', $codalmacen);
            }
        }
    }
}

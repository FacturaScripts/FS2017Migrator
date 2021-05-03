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

use FacturaScripts\Dinamic\Model\Almacen;
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Serie;

/**
 * Description of EmpresaMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EmpresaMigrator extends MigratorBase
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        foreach ($this->dataBase->select('SELECT * FROM empresa;') as $row) {
            $this->updateCompany($row);
        }
        $this->updateCountries();
        $this->updateSeries();

        foreach ($this->dataBase->select('SELECT * FROM fs_vars;') as $row) {
            $this->updatePreferences($row);
        }
        $this->toolBox()->appSettings()->save();
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
                if (false === \in_array($key, $exclude)) {
                    $empresa->{$key} = $value;
                }
            }

            if (false === $empresa->save()) {
                $this->toolBox()->i18nLog()->error('record-save-error');
                return;
            }

            $this->toolBox()->appSettings()->set('default', 'idempresa', $empresa->idempresa);
            $this->toolBox()->appSettings()->set('email', 'email', $empresa->email);
            $this->updateWarehouses($empresa->idempresa, $data['codalmacen']);
            $this->updatePaymentMethods($empresa->idempresa, $data['codpago']);
            $this->updateBankAccounts($empresa->idempresa);
            $this->updateAccounting($empresa->idempresa);
            break;
        }
    }

    protected function updateCountries()
    {
        $sql = "UPDATE paises SET codiso = null WHERE codiso = '';";
        $this->dataBase->exec($sql);
    }

    /**
     * 
     * @param int    $idempresa
     * @param string $codpago
     */
    protected function updatePaymentMethods($idempresa, $codpago)
    {
        $formaPagoModel = new FormaPago();
        foreach ($formaPagoModel->all() as $formaPago) {
            $formaPago->idempresa = $idempresa;

            if (isset($formaPago->codcuenta) && empty($formaPago->codcuentabanco)) {
                $formaPago->codcuentabanco = $formaPago->codcuenta;
            }

            if (isset($formaPago->genrecibos)) {
                $formaPago->pagado = ($formaPago->genrecibos == 'Pagados');
            }

            $this->setPaymentMehtodExpiration($formaPago);
            $formaPago->save();

            if ($formaPago->codpago == $codpago) {
                $this->toolBox()->appSettings()->set('default', 'codpago', $codpago);
            }
        }
    }

    /**
     * 
     * @param array $row
     */
    protected function updatePreferences($row)
    {
        switch ($row['name']) {
            case 'mail_bcc':
                $this->toolBox()->appSettings()->set('email', 'emailbcc', $row['varchar']);
                break;

            case 'mail_enc':
                $this->toolBox()->appSettings()->set('email', 'enc', $row['varchar']);
                break;

            case 'mail_firma':
                $this->toolBox()->appSettings()->set('email', 'signature', $row['varchar']);
                break;

            case 'mail_host':
                $this->toolBox()->appSettings()->set('email', 'host', $row['varchar']);
                break;

            case 'mail_mailer':
                $this->toolBox()->appSettings()->set('email', 'mailer', $row['varchar']);
                break;

            case 'mail_password':
                $this->toolBox()->appSettings()->set('email', 'password', $row['varchar']);
                break;

            case 'mail_port':
                $this->toolBox()->appSettings()->set('email', 'port', $row['varchar']);
                break;

            case 'mail_user':
                $this->toolBox()->appSettings()->set('email', 'user', $row['varchar']);
                break;
        }
    }

    protected function updateSeries()
    {
        foreach (['A', 'R', 'S'] as $codserie) {
            $serie = new Serie();
            if (!$serie->loadFromCode($codserie)) {
                $serie->codserie = $codserie;
                $serie->descripcion = $codserie;
                $serie->save();
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
                $this->toolBox()->appSettings()->set('default', 'codalmacen', $codalmacen);
            }
        }
    }

    /**
     * 
     * @param FormaPago $formaPago
     */
    protected function setPaymentMehtodExpiration(&$formaPago)
    {
        if (!isset($formaPago->vencimiento)) {
            return;
        }

        switch ($formaPago->vencimiento) {
            default:
                $formaPago->plazovencimiento = 0;
                $formaPago->tipovencimiento = 'days';
                break;

            case '+1day':
                $formaPago->plazovencimiento = 1;
                $formaPago->tipovencimiento = 'days';
                break;

            case '+1week':
                $formaPago->plazovencimiento = 1;
                $formaPago->tipovencimiento = 'weeks';
                break;

            case '+2week':
                $formaPago->plazovencimiento = 2;
                $formaPago->tipovencimiento = 'weeks';
                break;

            case '+3week':
                $formaPago->plazovencimiento = 3;
                $formaPago->tipovencimiento = 'weeks';
                break;

            case '+1month':
                $formaPago->plazovencimiento = 1;
                $formaPago->tipovencimiento = 'months';
                break;

            case '+2month':
                $formaPago->plazovencimiento = 2;
                $formaPago->tipovencimiento = 'months';
                break;

            case '+3month':
                $formaPago->plazovencimiento = 3;
                $formaPago->tipovencimiento = 'months';
                break;

            case '+4month':
                $formaPago->plazovencimiento = 4;
                $formaPago->tipovencimiento = 'months';
                break;

            case '+5month':
                $formaPago->plazovencimiento = 5;
                $formaPago->tipovencimiento = 'months';
                break;

            case '+6month':
                $formaPago->plazovencimiento = 6;
                $formaPago->tipovencimiento = 'months';
                break;

            case '+7month':
                $formaPago->plazovencimiento = 7;
                $formaPago->tipovencimiento = 'months';
                break;

            case '+8month':
                $formaPago->plazovencimiento = 8;
                $formaPago->tipovencimiento = 'months';
                break;

            case '+9month':
                $formaPago->plazovencimiento = 9;
                $formaPago->tipovencimiento = 'months';
                break;

            case '+10month':
                $formaPago->plazovencimiento = 10;
                $formaPago->tipovencimiento = 'months';
                break;

            case '+11month':
                $formaPago->plazovencimiento = 11;
                $formaPago->tipovencimiento = 'months';
                break;

            case '+12month':
                $formaPago->plazovencimiento = 12;
                $formaPago->tipovencimiento = 'months';
                break;
        }
    }
}

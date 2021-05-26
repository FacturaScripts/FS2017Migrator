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
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\CrmFuente;
use FacturaScripts\Dinamic\Model\GrupoClientes;

/**
 * Description of ContactosMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ContactosMigrator extends MigratorBase
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (false === $this->dataBase->tableExists('crm_contactos')) {
            return true;
        }

        if ($offset === 0) {
            $this->fixContactos();
        }

        $sql = "SELECT * FROM crm_contactos ORDER BY codcontacto ASC";
        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
        foreach ($rows as $row) {
            if (false === $this->newContact($row)) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    private function fixContactos()
    {
        $sql = "UPDATE crm_contactos SET codgrupo = null WHERE codgrupo NOT IN (SELECT codgrupo FROM gruposclientes);"
            . "UPDATE crm_contactos SET codagente = null WHERE codagente NOT IN (SELECT codagente FROM agentes);";
        $this->dataBase->exec($sql);
    }

    /**
     * 
     * @param string $name
     *
     * @return int
     */
    protected function getIdFuente($name)
    {
        $fuente = new CrmFuente();
        $where = [new DataBaseWhere('nombre', $this->toolBox()->utils()->noHtml($name))];
        if (false === $fuente->loadFromCode('', $where)) {
            /// create source
            $fuente->descripcion = $name;
            $fuente->nombre = $name;
            $fuente->save();
        }

        return $fuente->primaryColumnValue();
    }

    /**
     * 
     * @param Contacto $contact
     *
     * @return bool
     */
    protected function migrateGroup($contact): bool
    {
        if (!isset($contact->codgrupo) || empty($contact->codgrupo)) {
            return true;
        }

        $listClass = '\\FacturaScripts\\Dinamic\\Model\\CrmLista';
        $memberClass = '\\FacturaScripts\\Dinamic\\Model\\CrmListaContacto';
        if (false === \class_exists($listClass) || false === \class_exists($memberClass)) {
            return true;
        }

        $crmLista = new $listClass();
        $grupo = new GrupoClientes();
        if (false === $grupo->loadFromCode($contact->codgrupo) ||
            false === $crmLista->loadFromCode('', [new DataBaseWhere('nombre', $grupo->nombre)])) {
            $crmLista->nombre = $grupo->nombre;
            $crmLista->save();
        }

        $member = new $memberClass();
        $where = [
            new DataBaseWhere('idcontacto', $contact->idcontacto),
            new DataBaseWhere('idlista', $crmLista->id)
        ];
        if (false === $member->loadFromCode('', $where)) {
            $member->idcontacto = $contact->idcontacto;
            $member->idlista = $crmLista->id;
            $member->save();
        }

        return true;
    }

    /**
     * 
     * @param Contacto $contact
     * @param string   $codcontacto
     *
     * @return bool
     */
    protected function migrateNotes($contact, $codcontacto)
    {
        $class = '\\FacturaScripts\\Dinamic\\Model\\CrmNota';
        if (false === $this->dataBase->tableExists('crm_notas') ||
            false === \class_exists($class)) {
            return true;
        }

        $crmNote = new $class();
        $where = [
            new DataBaseWhere('codcontacto', $codcontacto),
            new DataBaseWhere('idcontacto', null, 'IS')
        ];
        foreach ($crmNote->all($where, [], 0, 0) as $note) {
            $note->idcontacto = $contact->idcontacto;
            $note->save();
        }

        return true;
    }

    /**
     * 
     * @param Contacto $contact
     * @param string   $codcontacto
     *
     * @return bool
     */
    protected function migrateOportunities($contact, $codcontacto)
    {
        $class = '\\FacturaScripts\\Dinamic\\Model\\CrmOportunidad';
        if (false === $this->dataBase->tableExists('crm_oportunidades') ||
            false === \class_exists($class)) {
            return true;
        }

        $crmOportunity = new $class();
        $where = [
            new DataBaseWhere('codcontacto', $codcontacto),
            new DataBaseWhere('idcontacto', null, 'IS')
        ];
        foreach ($crmOportunity->all($where, [], 0, 0) as $opo) {
            $opo->idcontacto = $contact->idcontacto;

            switch ($opo->estado) {
                case 'nuevo':
                    $opo->idestado = 1;
                    break;

                case 'negociando':
                case 'presupuestando':
                    $opo->idestado = 2;
                    break;

                case 'enviado':
                case 'espera':
                    $opo->idestado = 3;
                    break;

                case 'aceptado':
                    $opo->idestado = 4;
                    break;

                case 'rechazado':
                    $opo->idestado = 5;
                    break;
            }

            $opo->save();
        }

        return true;
    }

    /**
     * 
     * @param array $data
     *
     * @return bool
     */
    protected function newContact($data): bool
    {
        $contact = new Contacto();
        $where = empty($data['email']) ? [new DataBaseWhere('nombre', $data['nombre'])] : [new DataBaseWhere('email', $data['email'])];
        if ($contact->loadFromCode('', $where)) {
            return $this->migrateNotes($contact, $data['codcontacto']) &&
                $this->migrateGroup($contact) &&
                $this->migrateOportunities($contact, $data['codcontacto']);
        }

        $data['cifnif'] = $data['nif'] ?? '';
        $data['email'] = \filter_var($data['email'], \FILTER_VALIDATE_EMAIL) ? $data['email'] : '';

        $emails = $this->getEmails($data['email']);
        $data['email'] = empty($emails) ? '' : $emails[0];
        foreach ($emails as $num => $email) {
            if ($num > 0) {
                $data['observaciones'] .= "\n" . $email;
            }
        }

        if (empty($data['nombre']) && empty($data['direccion'])) {
            $data['descripcion'] = $data['codcontacto'];
        }

        if (empty($data['nombre'])) {
            $data['nombre'] = '-';
        }

        $contact->loadFromData($data);
        if (isset($data['fuente'])) {
            $contact->idfuente = $this->getIdFuente($data['fuente']);
        }

        return $contact->save() && $this->migrateNotes($contact, $data['codcontacto']) &&
            $this->migrateGroup($contact) &&
            $this->migrateOportunities($contact, $data['codcontacto']);
    }
}

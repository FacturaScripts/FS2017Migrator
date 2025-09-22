<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\FS2017Migrator\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;

/**
 * Description of CrmFuente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class CrmFuente extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $descripcion;

    /** @var string */
    public $fecha;

    /** @var int */
    public $id;

    /** @var string */
    public $nombre;

    /** @var int */
    public $numcontactos;

    public function clear(): void
    {
        parent::clear();
        $this->fecha = Tools::date();
        $this->numcontactos = 0;
    }

    public function primaryDescriptionColumn(): string
    {
        return 'nombre';
    }

    public static function tableName(): string
    {
        return 'crm_fuentes2';
    }

    public function test(): bool
    {
        $this->descripcion = Tools::noHtml($this->descripcion);
        $this->nombre = Tools::noHtml($this->nombre);

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ListContacto?activetab=List'): string
    {
        return parent::url($type, $list);
    }

    protected function saveUpdate(): bool
    {
        // get the number of contacts with this source
        $where = [Where::eq('idfuente', $this->id())];
        $this->numcontactos = Contacto::count($where);

        return parent::saveUpdate();
    }
}

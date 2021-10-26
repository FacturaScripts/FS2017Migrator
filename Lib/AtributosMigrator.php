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

use FacturaScripts\Dinamic\Model\AtributoValor;

/**
 * Description of AtributosMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class AtributosMigrator extends MigratorBase
{

    /**
     *
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        $this->removeDuplicatedValues();

        $attValorModel = new AtributoValor();
        foreach ($attValorModel->all([], ['id' => 'ASC'], $offset) as $valor) {
            if (false === $valor->save()) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    private function removeDuplicatedValues()
    {
        if (false === $this->dataBase->tableExists('atributos_valores')) {
            return;
        }

        $sql = 'select codatributo,valor from atributos_valores group by codatributo,valor having count(*) > 1;';
        foreach ($this->dataBase->select($sql) as $row) {
            $sql2 = 'select * from atributos_valores'
                . ' where codatributo = ' . $this->dataBase->var2str($row['codatributo'])
                . ' and valor = ' . $this->dataBase->var2str($row['valor']) . ';';
            foreach ($this->dataBase->select($sql2) as $row2) {
                $sql3 = 'delete from atributos_valores where id = ' . $this->dataBase->var2str($row2['id']) . ';';
                $this->dataBase->exec($sql3);
                break;
            }
        }
    }
}

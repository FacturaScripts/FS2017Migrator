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

use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Diario;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Partida;

/**
 * Description of AsientosMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class AsientosMigrator extends MigratorBase
{

    /**
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        switch ($offset) {
            case 0:
                $offset++;
                return $this->renameTable('co_asientos', 'asientos');

            case 1:
                $offset++;
                return $this->renameTable('co_partidas', 'partidas');

            case 2:
                $offset++;
                new Diario();
                new Asiento();
                return $this->dataBase->tableExists('asientos');

            case 3:
                $offset++;
                return $this->fixPartidas();

            case 4:
                $offset++;
                new Partida();
                return $this->dataBase->tableExists('partidas');

            case 5:
                $offset++;
                $idempresa = (int)$this->toolBox()->appSettings()->get('default', 'idempresa');
                $sql = "UPDATE asientos SET idempresa = " . $this->dataBase->var2str($idempresa)
                    . " WHERE idempresa IS NULL;"
                    . " UPDATE partidas SET idcontrapartida = null WHERE idcontrapartida IS NOT NULL"
                    . " AND idcontrapartida NOT IN (SELECT idsubcuenta FROM subcuentas)";
                return $this->dataBase->exec($sql);

            case 6:
                return $this->migrateSpecialEntries();
        }

        return true;
    }

    /**
     * @return bool
     */
    private function fixPartidas(): bool
    {
        if (false === $this->dataBase->tableExists('partidas')) {
            return true;
        }

        $sql = 'DELETE FROM partidas WHERE idasiento NOT IN (SELECT idasiento FROM asientos)';
        return $this->dataBase->exec($sql);
    }

    private function migrateSpecialEntries()
    {
        $map = [
            'idasientoapertura' => Asiento::OPERATION_OPENING,
            'idasientocierre' => Asiento::OPERATION_CLOSING,
            'idasientopyg' => Asiento::OPERATION_REGULARIZATION
        ];

        $exerciseModel = new Ejercicio();
        foreach ($exerciseModel->all() as $exercise) {
            foreach ($map as $key => $operation) {
                $sql = "UPDATE " . Asiento::tableName()
                    . " SET operacion = " . $this->dataBase->var2str($operation)
                    . " WHERE idasiento = " . $this->dataBase->var2str($exercise->{$key});
                if (false === $this->dataBase->exec($sql)) {
                    return false;
                }
            }
        }

        return true;
    }
}

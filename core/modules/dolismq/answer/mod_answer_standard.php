<?php
/* Copyright (C) 2022 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *  \file    core/modules/dolismq/answer/mod_answer_standard.php
 *  \ingroup dolismq
 *  \brief   File of class to manage answer numbering rules standard.
 */

// Load Saturne libraries.
require_once __DIR__ . '/../../../../../saturne/core/modules/saturne/modules_saturne.php';

/**
 *	Class to manage answer numbering rules standard.
 */
class mod_answer_standard extends ModeleNumRefSaturne
{
	/**
	 * @var string Numbering module ref prefix.
	 */
	public string $prefix = 'AN';

	/**
	 * @var string Name.
	 */
	public string $name = 'Hati';
}

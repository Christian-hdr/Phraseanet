<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2014 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Model\Repositories;

use Doctrine\ORM\EntityRepository;

/**
 * SessionRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SessionRepository extends EntityRepository
{
    public function findByUser(\User_Adapter $user)
    {
        $dql = 'SELECT s
            FROM Phraseanet:Session s
            WHERE s.usr_id = :usr_id';

        $query = $this->_em->createQuery($dql);
        $query->setParameters(['usr_id' => $user->get_id()]);

        return $query->getResult();
    }
}

<?php
/**
 * @package Newscoop
 * @copyright 2011 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\News;

/**
 * Item Service
 */
class ItemService
{
    /**
     * @var Doctrine\Common\Persistence\ObjectManager
     */
    protected $om;

    /**
     * @var Doctrine\Common\Persistence\ObjectRepository
     */
    protected $repository;

    /**
     * @var Doctrine\Common\Persistence\ObjectManager @om
     */
    public function __construct(\Doctrine\Common\Persistence\ObjectManager $om)
    {
        $this->om = $om;
        $this->repository = $this->om->getRepository('Newscoop\News\NewsItem');
    }

    /**
     * Find feeds by set of criteria
     *
     * @param array $criteria
     * @param mixed $orderBy
     * @param mixed $limit
     * @param mixed $offset
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->repository->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Persist item
     *
     * @param Newscoop\News\Item $item
     * @return void
     */
    public function persist(Item $item)
    {
        $persisted = $this->repository->find($item->getId());
        if ($persisted) {
            if ($persisted->getVersion() < $item->getVersion()) {
                $persisted->update($item);
            }
        } else {
            $this->om->persist($item);
            $persisted = $item;
        }
    }
}

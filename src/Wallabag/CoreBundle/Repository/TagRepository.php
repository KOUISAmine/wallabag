<?php

namespace Wallabag\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Wallabag\CoreBundle\Entity\Tag;

class TagRepository extends EntityRepository
{
    /**
     * Count all tags per user.
     *
     * @param int $userId
     * @param int $cacheLifeTime Duration of the cache for this query
     *
     * @return int
     */
    public function countAllTags($userId, $cacheLifeTime = null)
    {
        $query = $this->createQueryBuilder('t')
            ->select('t.slug')
            ->leftJoin('t.entries', 'e')
            ->where('e.user = :userId')->setParameter('userId', $userId)
            ->groupBy('t.slug')
            ->getQuery();

        if (null !== $cacheLifeTime) {
            $query->useQueryCache(true);
            $query->useResultCache(true);
            $query->setResultCacheLifetime($cacheLifeTime);
        }

        return count($query->getArrayResult());
    }

    /**
     * Find all tags per user.
     * Instead of just left joined on the Entry table, we select only id and group by id to avoid tag multiplication in results.
     * Once we have all tags id, we can safely request them one by one.
     * This'll still be fastest than the previous query.
     *
     * @param int $userId
     *
     * @return array
     */
    public function findAllTags($userId)
    {
        $ids = $this->createQueryBuilder('t')
            ->select('t.id')
            ->leftJoin('t.entries', 'e')
            ->where('e.user = :userId')->setParameter('userId', $userId)
            ->groupBy('t.id')
            ->orderBy('t.slug')
            ->getQuery()
            ->getArrayResult();

        $tags = [];
        foreach ($ids as $id) {
            $tags[] = $this->find($id);
        }

        return $tags;
    }

    /**
     * Find all tags (flat) per user with nb entries.
     *
     * @param int $userId
     *
     * @return array
     */
    public function findAllFlatTagsWithNbEntries($userId)
    {
        return $this->createQueryBuilder('t')
            ->select('t.id, t.label, t.slug, count(e.id) as nbEntries')
            ->distinct(true)
            ->leftJoin('t.entries', 'e')
            ->where('e.user = :userId')
            ->groupBy('t.id')
            ->orderBy('t.slug')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Used only in test case to get a tag for our entry.
     *
     * @return Tag
     */
    public function findOneByEntryAndTagLabel($entry, $label)
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.entries', 'e')
            ->where('e.id = :entryId')->setParameter('entryId', $entry->getId())
            ->andWhere('t.label = :label')->setParameter('label', $label)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult();
    }

    public function findForArchivedArticlesByUser($userId)
    {
        $ids = $this->createQueryBuilder('t')
            ->select('t.id')
            ->leftJoin('t.entries', 'e')
            ->where('e.user = :userId')->setParameter('userId', $userId)
            ->andWhere('e.isArchived = true')
            ->groupBy('t.id')
            ->orderBy('t.slug')
            ->getQuery()
            ->getArrayResult();

        $tags = [];
        foreach ($ids as $id) {
            $tags[] = $this->find($id);
        }

        return $tags;
    }
}

<?php
namespace Ilios\CoreBundle\Entity\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Ilios\CoreBundle\Entity\DTO\CurriculumInventoryReportDTO;
use Ilios\CoreBundle\Entity\CurriculumInventoryReportInterface;

/**
 * Class CurriculumInventoryReportRepository
 */
class CurriculumInventoryReportRepository extends EntityRepository implements DTORepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('DISTINCT x')->from('IliosCoreBundle:CurriculumInventoryReport', 'x');

        $this->attachCriteriaToQueryBuilder($qb, $criteria, $orderBy, $limit, $offset);

        return $qb->getQuery()->getResult();
    }

    /**
     * Find and hydrate as DTOs
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @param null $limit
     * @param null $offset
     *
     * @return array
     */
    public function findDTOsBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $qb = $this->_em->createQueryBuilder()->select('x')
            ->distinct()->from('IliosCoreBundle:CurriculumInventoryReport', 'x');
        $this->attachCriteriaToQueryBuilder($qb, $criteria, $orderBy, $limit, $offset);

        /** @var CurriculumInventoryReportDTO[] $curriculumInventoryReportDTOs */
        $curriculumInventoryReportDTOs = [];
        foreach ($qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY) as $arr) {
            $curriculumInventoryReportDTOs[$arr['id']] = new CurriculumInventoryReportDTO(
                $arr['id'],
                $arr['name'],
                $arr['description'],
                $arr['year'],
                $arr['startDate'],
                $arr['endDate'],
                $arr['token']
            );
        }
        $curriculumInventoryReportIds = array_keys($curriculumInventoryReportDTOs);

        $qb = $this->_em->createQueryBuilder()
            ->select(
                'x.id as xId, ' .
                'export.id AS exportId, sequence.id AS sequenceId, program.id AS programId, ' .
                'school.id AS schoolId'
            )
            ->from('IliosCoreBundle:CurriculumInventoryReport', 'x')
            ->join('x.program', 'program')
            ->join('program.school', 'school')
            ->leftJoin('x.sequence', 'sequence')
            ->leftJoin('x.export', 'export')
            ->where($qb->expr()->in('x.id', ':ids'))
            ->setParameter('ids', $curriculumInventoryReportIds);

        foreach ($qb->getQuery()->getResult() as $arr) {
            $curriculumInventoryReportDTOs[$arr['xId']]->export = $arr['exportId']?(int)$arr['exportId']:null;
            $curriculumInventoryReportDTOs[$arr['xId']]->sequence = $arr['sequenceId']?(int)$arr['sequenceId']:null;
            $curriculumInventoryReportDTOs[$arr['xId']]->program = $arr['programId']?(int)$arr['programId']:null;
            $curriculumInventoryReportDTOs[$arr['xId']]->school = $arr['schoolId']?(int)$arr['schoolId']:null;
        }

        $related = [
            'sequenceBlocks',
            'academicLevels',
            'administrators',
        ];
        foreach ($related as $rel) {
            $qb = $this->_em->createQueryBuilder()
                ->select('r.id AS relId, x.id AS curriculumInventoryReportId')
                ->from('IliosCoreBundle:CurriculumInventoryReport', 'x')
                ->join("x.{$rel}", 'r')
                ->where($qb->expr()->in('x.id', ':ids'))
                ->orderBy('relId')
                ->setParameter('ids', $curriculumInventoryReportIds);
            foreach ($qb->getQuery()->getResult() as $arr) {
                $curriculumInventoryReportDTOs[$arr['curriculumInventoryReportId']]->{$rel}[] = $arr['relId'];
            }
        }
        return array_values($curriculumInventoryReportDTOs);
    }


    /**
     * @param QueryBuilder $qb
     * @param array $criteria
     * @param array $orderBy
     * @param int $limit
     * @param int $offset
     *
     * @return QueryBuilder
     */
    protected function attachCriteriaToQueryBuilder(QueryBuilder $qb, $criteria, $orderBy, $limit, $offset)
    {
        if (array_key_exists('sequenceBlocks', $criteria)) {
            $ids = is_array($criteria['sequenceBlocks']) ? $criteria['sequenceBlocks'] : [$criteria['sequenceBlocks']];
            $qb->join('x.sequenceBlocks', 'sb');
            $qb->andWhere($qb->expr()->in('sb.id', ':sequenceBlocks'));
            $qb->setParameter(':sequenceBlocks', $ids);
        }
        if (array_key_exists('academicLevels', $criteria)) {
            $ids = is_array($criteria['academicLevels']) ? $criteria['academicLevels'] : [$criteria['academicLevels']];
            $qb->join('x.academicLevels', 'al');
            $qb->andWhere($qb->expr()->in('al.id', ':academicLevels'));
            $qb->setParameter(':academicLevels', $ids);
        }

        //cleanup all the possible relationship filters
        unset($criteria['sequenceBlocks']);
        unset($criteria['academicLevels']);

        if (count($criteria)) {
            foreach ($criteria as $key => $value) {
                $values = is_array($value) ? $value : [$value];
                $qb->andWhere($qb->expr()->in("x.{$key}", ":{$key}"));
                $qb->setParameter(":{$key}", $values);
            }
        }

        if (empty($orderBy)) {
            $orderBy = ['id' => 'ASC'];
        }

        if (is_array($orderBy)) {
            foreach ($orderBy as $sort => $order) {
                $qb->addOrderBy('x.'.$sort, $order);
            }
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb;
    }

    /**
     * Retrieves a list of events (derived from published sessions/offerings and independent learning sessions)
     * in a given curriculum inventory report.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array An assoc. array of assoc. arrays, each item representing an event, keyed off by event id.
     */
    public function getEvents(CurriculumInventoryReportInterface $report)
    {
        // WHAT'S GOING ON HERE?!
        // Aggregate the CI events retrieved from session-offerings with the events retrieved from ILM sessions.
        // We can't do this by ways of <code>array_merge()</code>, since this would clobber the keys on the joined array
        // (we're dealing with associative arrays using numeric keys here).
        // Hence the use of the '+' array-operator.
        // This should be OK since there is no overlap of elements between the two source arrays.
        // [ST 2015/09/18]
        // @link http://php.net/manual/en/language.operators.array.php
        // @link http://php.net/manual/en/function.array-merge.php
        $rhett = $this->getEventsFromSessionOfferings($report) + $this->getEventsFromIlmSessions($report);
        return $rhett;
    }

    /**
     * Retrieves AAMC resource types associated with events (sessions) in a given curriculum inventory report.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array An array of assoc. arrays, each sub-array representing a resource type.
     */
    public function getEventResourceTypes(CurriculumInventoryReportInterface $report)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('s.id AS event_id, art.id AS resource_type_id, art.title AS resource_type_title')
            ->distinct()
            ->from('IliosCoreBundle:Session', 's')
            ->join('s.course', 'c')
            ->join('c.sequenceBlocks', 'sb')
            ->join('sb.report', 'r')
            ->join('s.terms', 't')
            ->join('t.aamcResourceTypes', 'art')
            ->where($qb->expr()->eq('s.published', 1))
            ->andWhere($qb->expr()->eq('r.id', ':id'))
            ->setParameter('id', $report->getId());
        return $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
    }

    /**
     * Retrieves keywords (MeSH descriptors) associated with events (sessions)
     * in a given curriculum inventory report.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array An array of assoc. arrays, each sub-array representing a keyword.
     */
    public function getEventKeywords(CurriculumInventoryReportInterface $report)
    {
        $rhett = [];
        $qb = $this->_em->createQueryBuilder();
        $qb->select("s.id AS event_id, md.id, 'MeSH' AS source, md.name")
            ->from('IliosCoreBundle:Session', 's')
            ->join('s.course', 'c')
            ->join('c.sequenceBlocks', 'sb')
            ->join('sb.report', 'r')
            ->join('s.meshDescriptors', 'md')
            ->where($qb->expr()->eq('s.published', 1))
            ->andWhere($qb->expr()->eq('r.id', ':id'))
            ->setParameter('id', $report->getId());
        $queries[] = $qb->getQuery();
        $qb = $this->_em->createQueryBuilder();
        $qb->select("s.id AS event_id, t.id, v.title AS source, t.title AS name")
            ->from('IliosCoreBundle:Session', 's')
            ->join('s.course', 'c')
            ->join('c.sequenceBlocks', 'sb')
            ->join('sb.report', 'r')
            ->join('s.terms', 't')
            ->join('t.vocabulary', 'v')
            ->where($qb->expr()->eq('s.published', 1))
            ->andWhere($qb->expr()->eq('r.id', ':id'))
            ->setParameter('id', $report->getId());
        $queries[] = $qb->getQuery();
        foreach ($queries as $query) {
            $rhett = array_merge($rhett, $query->getResult(AbstractQuery::HYDRATE_ARRAY));
        }
        return $rhett;
    }

    /**
     * Retrieves a lookup map of events ('sessions') in a given curriculum inventory report,
     * grouped and keyed off by sequence block id.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array
     */
    public function getEventReferencesForSequenceBlocks(CurriculumInventoryReportInterface $report)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('sb.id, s.id AS event_id, s.supplemental AS optional')
            ->from('IliosCoreBundle:Session', 's')
            ->join('s.course', 'c')
            ->join('c.sequenceBlocks', 'sb')
            ->join('sb.report', 'r')
            ->where($qb->expr()->eq('s.published', 1))
            ->andWhere($qb->expr()->eq('r.id', ':id'))
            ->setParameter('id', $report->getId());
        $rows = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        $rhett = [];
        foreach ($rows as $row) {
            if (! array_key_exists($row['id'], $rhett)) {
                $rhett[$row['id']] = [];
            }
            $rhett[$row['id']][] = $row;
        }
        return $rhett;
    }

    /**
     * Retrieves all program objectives in a given curriculum inventory report.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array An associative array of arrays, keyed off by objective id.
     *   Each item is an associative array, containing
     *   the objective's id and title (keys: "objective_id" and "title").
     */
    public function getProgramObjectives(CurriculumInventoryReportInterface $report)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('o.id, o.title')
            ->distinct()
            ->from('IliosCoreBundle:CurriculumInventoryReport', 'r')
            ->join('r.program', 'p')
            ->join('p.school', 's')
            ->join('r.sequenceBlocks', 'sb')
            ->join('sb.course', 'c')
            ->join('c.cohorts', 'co')
            ->join('co.programYear', 'py')
            ->join('py.program', 'p2')
            ->join('p2.school', 's2')
            ->join('py.objectives', 'o')
            ->where($qb->expr()->eq('s.id', 's2.id'))
            ->andWhere($qb->expr()->eq('r.id', ':id'))
            ->setParameter('id', $report->getId());

        $rows = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        $rhett = [];
        foreach ($rows as $row) {
            $rhett[$row['id']] = $row;
        }
        return $rhett;
    }

    /**
     * Retrieves all course objectives in a given curriculum inventory report.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array an associative array of arrays, keyed off by objective id.
     *   Each item is an associative array, containing
     *   the objective's id and title (keys: "objective_id" and "title").
     */
    public function getCourseObjectives(CurriculumInventoryReportInterface $report)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('o.id, o.title')
            ->distinct()
            ->from('IliosCoreBundle:CurriculumInventoryReport', 'r')
            ->join('r.program', 'p')
            ->join('r.sequenceBlocks', 'sb')
            ->join('sb.course', 'c')
            ->join('c.objectives', 'o')
            ->where($qb->expr()->eq('r.id', ':id'))
            ->setParameter('id', $report->getId());

        $rows = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        $rhett = [];
        foreach ($rows as $row) {
            $rhett[$row['id']] = $row;
        }
        return $rhett;
    }

    /**
     * Retrieves all session objectives in a given curriculum inventory report.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array An associative array of arrays, keyed off by objective id.
     *   Each item is an associative array, containing
     *   the objective's id and title (keys: "objective_id" and "title").
     */
    public function getSessionObjectives(CurriculumInventoryReportInterface $report)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('o.id, o.title')
            ->distinct()
            ->from('IliosCoreBundle:CurriculumInventoryReport', 'r')
            ->join('r.program', 'p')
            ->join('r.sequenceBlocks', 'sb')
            ->join('sb.course', 'c')
            ->join('c.sessions', 's')
            ->join('s.objectives', 'o')
            ->where($qb->expr()->eq('s.published', 1))
            ->andWhere($qb->expr()->eq('r.id', ':id'))
            ->setParameter('id', $report->getId());

        $rows = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        $rhett = [];
        foreach ($rows as $row) {
            $rhett[$row['id']] = $row;
        }
        return $rhett;
    }

    /**
     * Retrieves all the competency object references per event in a given report.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array An associative array of arrays, keyed off by event id.
     *     Each sub-array is in turn a two item map, containing a list of course objectives ids
     *     under 'course_objectives', a list of program objective ids under 'program_objective'
     *     and a list of session objective ids under under 'session_objective_ids'.
     *
     *   <pre>
     *   [ <sequence block id> => [
     *       "course_objectives" => [ <list of course objectives ids> ]
     *       "program_objectives" => [ <list of program objective ids> ]
     *       "session_objectives" => [ <list of session objective ids> ]
     *     ],
     *     ...
     *   ],
     *   </pre>
     */
    public function getCompetencyObjectReferencesForEvents(CurriculumInventoryReportInterface $report)
    {
        $rhett = [];
        $sql =<<<EOL
SELECT DISTINCT
  s.session_id AS 'event_id',
  so.objective_id AS 'session_objective_id',
  o.objective_id as 'course_objective_id',
  o2.objective_id AS 'program_objective_id'
FROM
  curriculum_inventory_report r
  JOIN program p ON p.program_id = r.program_id
  JOIN curriculum_inventory_sequence_block sb ON sb.report_id = r.report_id
  JOIN course c ON c.course_id = sb.course_id
  JOIN `session` s ON s.course_id = c.course_id
  JOIN session_x_objective sxo ON sxo.session_id = s.session_id
  JOIN objective so ON so.objective_id = sxo.objective_id
  LEFT JOIN objective_x_objective oxo ON oxo.objective_id = so.objective_id
  LEFT JOIN course_x_objective cxo ON cxo.objective_id = oxo.parent_objective_id
  LEFT JOIN objective o ON o.objective_id = cxo.objective_id
  LEFT JOIN objective_x_objective oxo2 ON oxo2.objective_id = o.objective_id
  LEFT JOIN objective o2 ON o2.objective_id = oxo2.parent_objective_id
WHERE
  s.published
  AND r.report_id = :report_id
EOL;
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindValue("report_id", $report->getId());
        $stmt->execute();
        $rows =  $stmt->fetchAll();
        foreach ($rows as $row) {
            $eventId = $row['event_id'];
            if (! array_key_exists($eventId, $rhett)) {
                $rhett[$eventId] = [
                    'session_objectives' => [],
                    'course_objectives' => [],
                    'program_objectives' => [],
                ];
            }
            if (isset($row['session_objective_id'])
                && ! in_array($row['session_objective_id'], $rhett[$eventId]['session_objectives'])) {
                $rhett[$eventId]['session_objectives'][] = $row['session_objective_id'];
            }
            if (isset($row['course_objective_id'])
                && ! in_array($row['course_objective_id'], $rhett[$eventId]['course_objectives'])) {
                $rhett[$eventId]['course_objectives'][] = $row['course_objective_id'];
            }
            if (isset($row['program_objective_id'])
                && ! in_array($row['program_objective_id'], $rhett[$eventId]['program_objectives'])) {
                $rhett[$eventId]['program_objectives'][] = $row['program_objective_id'];
            }
        }
        $stmt->closeCursor();
        return $rhett;
    }

    /**
     * Retrieves all the competency object references per sequence block in a given report.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array An associative array of arrays, keyed off by sequence block id.
     *     Each sub-array is in turn a two item map, containing a list of course objectives ids
     *     under 'course_objectives' and a list of program objective ids under 'program_objective'.
     *
     *   <pre>
     *   [ <sequence block id> => [
     *       "course_objectives" => [ <list of course objectives ids> ]
     *       "program_objectives" => [ <list of program objective ids> ]
     *     ],
     *     ...
     *   ],
     *   </pre>
     */
    public function getCompetencyObjectReferencesForSequenceBlocks(CurriculumInventoryReportInterface $report)
    {
        $rhett = [];
        $qb = $this->_em->createQueryBuilder();
        $qb->select('sb.id, co.id AS course_objective_id, po.id AS program_objective_id')
            ->distinct()
            ->from('IliosCoreBundle:CurriculumInventoryReport', 'r')
            ->join('r.program', 'p')
            ->join('p.school', 's')
            ->join('r.sequenceBlocks', 'sb')
            ->join('sb.course', 'c')
            ->leftJoin('c.objectives', 'co')
            ->leftJoin('co.parents', 'po')
            ->where($qb->expr()->eq('r.id', ':id'))
            ->setParameter('id', $report->getId());

        $rows = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        foreach ($rows as $row) {
            $sequenceBlockId = $row['id'];
            if (! array_key_exists($sequenceBlockId, $rhett)) {
                $rhett[$sequenceBlockId] = [
                    'course_objectives' => [],
                    'program_objectives' => [],
                ];
            }
            if (isset($row['course_objective_id'])
                && ! in_array($row['course_objective_id'], $rhett[$sequenceBlockId]['course_objectives'])) {
                $rhett[$sequenceBlockId]['course_objectives'][] = $row['course_objective_id'];
            }
            if (isset($row['program_objective_id'])
                && ! in_array($row['program_objective_id'], $rhett[$sequenceBlockId]['program_objectives'])) {
                $rhett[$sequenceBlockId]['program_objectives'][] = $row['program_objective_id'];
            }
        }
        return $rhett;
    }

    /**
     * Retrieves the relations between given program-objectives and PCRS (via competencies).
     * @param array $programObjectivesId
     * @param array $pcrsIds
     * @return array
     */
    public function getProgramObjectivesToPcrsRelations(array $programObjectivesId, array $pcrsIds)
    {
        $rhett = [
            'relations' => [],
            'program_objective_ids' => [],
            'pcrs_ids' => [],
        ];

        if (! count($programObjectivesId) || ! count($pcrsIds)) {
            return $rhett;
        }

        $qb = $this->_em->createQueryBuilder();
        $qb->select('o.id as objective_id, am.id AS pcrs_id')
            ->distinct()
            ->from('IliosCoreBundle:Objective', 'o')
            ->join('o.competency', 'c')
            ->join('c.aamcPcrses', 'am')
            ->where($qb->expr()->in('am.id', ':pcrs'))
            ->andWhere($qb->expr()->in('o.id', ':objectives'))
            ->setParameter(':pcrs', $pcrsIds)
            ->setParameter(':objectives', $programObjectivesId);

        $rows = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);

        foreach ($rows as $row) {
            $rhett['relations'][] = [
                'rel1' => $row['objective_id'],
                'rel2' => $row['pcrs_id'],
            ];
            $rhett['program_objective_ids'][] = $row['objective_id'];
            $rhett['pcrs_ids'][] = $row['pcrs_id'];
        }

        // dedupe
        $rhett['program_objective_ids'] = array_values(array_unique($rhett['program_objective_ids']));
        $rhett['pcrs_ids'] = array_values(array_unique($rhett['pcrs_ids']));

        return $rhett;
    }

    /**
     * Retrieves the relations between given course- and program-objectives.
     * @param array $courseObjectiveIds
     * @param array $programObjectiveIds
     * @return array
     */
    public function getCourseObjectivesToProgramObjectivesRelations(
        array $courseObjectiveIds,
        array $programObjectiveIds
    ) {
        $rhett = [
            'relations' => [],
            'course_objective_ids' => [],
            'program_objective_ids' => [],
        ];

        if (! count($courseObjectiveIds) || ! count($programObjectiveIds)) {
            return $rhett;
        }

        $qb = $this->_em->createQueryBuilder();
        $qb->select('o.id AS objective_id, p.id AS parent_objective_id')
            ->distinct()
            ->from('IliosCoreBundle:Objective', 'o')
            ->join('o.courses', 'c')
            ->join('o.parents', 'p')
            ->where($qb->expr()->in('p.id', ':programObjectiveIds'))
            ->andWhere($qb->expr()->in('o.id', ':courseObjectiveIds'))
            ->setParameter(':courseObjectiveIds', $courseObjectiveIds)
            ->setParameter(':programObjectiveIds', $programObjectiveIds);

        $rows = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);

        foreach ($rows as $row) {
            $rhett['relations'][] = [
                'rel1' => $row['parent_objective_id'],
                'rel2' => $row['objective_id'],
            ];
            $rhett['course_objective_ids'][] = $row['objective_id'];
            $rhett['program_objective_ids'][] = $row['parent_objective_id'];
        }

        // dedupe
        $rhett['course_objective_ids'] = array_values(array_unique($rhett['course_objective_ids']));
        $rhett['program_objective_ids'] = array_values(array_unique($rhett['program_objective_ids']));

        return $rhett;
    }

    /**
     * Retrieves the relations between given session- and course-objectives.
     *
     * @param array $sessionObjectiveIds
     * @param array $courseObjectiveIds
     * @return array
     */
    public function getSessionObjectivesToCourseObjectivesRelations(
        array $sessionObjectiveIds,
        array $courseObjectiveIds
    ) {
        $rhett = [
            'relations' => [],
            'session_objective_ids' => [],
            'course_objective_ids' => [],
        ];

        if (! count($sessionObjectiveIds) || ! count($courseObjectiveIds)) {
            return $rhett;
        }

        $qb = $this->_em->createQueryBuilder();
        $qb->select('o.id AS objective_id, p.id AS parent_objective_id')
            ->distinct()
            ->from('IliosCoreBundle:Objective', 'o')
            ->join('o.sessions', 's')
            ->join('o.parents', 'p')
            ->where($qb->expr()->in('p.id', ':courseObjectiveIds'))
            ->andWhere($qb->expr()->in('o.id', ':sessionObjectiveIds'))
            ->setParameter(':sessionObjectiveIds', $sessionObjectiveIds)
            ->setParameter(':courseObjectiveIds', $courseObjectiveIds);

        $rows = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        foreach ($rows as $row) {
            $rhett['relations'][] = [
                'rel1' => $row['parent_objective_id'],
                'rel2' => $row['objective_id'],
            ];
            $rhett['session_objective_ids'][] = $row['objective_id'];
            $rhett['course_objective_ids'][] = $row['parent_objective_id'];
        }

        // dedupe
        $rhett['session_objective_ids'] = array_values(array_unique($rhett['session_objective_ids']));
        $rhett['course_objective_ids'] = array_values(array_unique($rhett['course_objective_ids']));

        return $rhett;
    }

    /**
     * Retrieves all PCRS linked to sequence blocks (via objectives and competencies) in a given inventory report.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array A nested array of associative arrays, keyed off by 'pcrs_id'. Each sub-array represents a PCRS
     *    and is itself an associative array with values being keyed off by 'pcrs_id' and 'description'.
     */
    public function getPcrs(CurriculumInventoryReportInterface $report)
    {
        $rhett = [];
        $qb = $this->_em->createQueryBuilder();
        $qb->select('am.id AS pcrs_id, am.description')
            ->from('IliosCoreBundle:CurriculumInventoryReport', 'r')
            ->join('r.program', 'p')
            ->join('p.school', 's')
            ->join('r.sequenceBlocks', 'sb')
            ->join('sb.course', 'c')
            ->join('c.cohorts', 'co')
            ->join('co.programYear', 'py')
            ->join('py.objectives', 'o')
            ->join('o.competency', 'cm')
            ->join('cm.school', 's2')
            ->join('cm.parent', 'cm2')
            ->join('cm2.aamcPcrses', 'am')
            ->where($qb->expr()->eq('s.id', 's2.id'))
            ->andWhere($qb->expr()->in('r.id', ':id'))
            ->setParameter(':id', $report->getId());
        $queries[] = $qb->getQuery();

        $qb = $this->_em->createQueryBuilder();
        $qb->select('am.id AS pcrs_id, am.description')
            ->from('IliosCoreBundle:CurriculumInventoryReport', 'r')
            ->join('r.program', 'p')
            ->join('p.school', 's')
            ->join('r.sequenceBlocks', 'sb')
            ->join('sb.course', 'c')
            ->join('c.cohorts', 'co')
            ->join('co.programYear', 'py')
            ->join('py.objectives', 'o')
            ->join('o.competency', 'cm')
            ->join('cm.school', 's2')
            ->join('cm.aamcPcrses', 'am')
            ->where($qb->expr()->eq('s.id', 's2.id'))
            ->andWhere($qb->expr()->in('r.id', ':id'))
            ->setParameter(':id', $report->getId());
        $queries[] = $qb->getQuery();

        foreach ($queries as $query) {
            $rows = $query->getResult(AbstractQuery::HYDRATE_ARRAY);
            foreach ($rows as $row) {
                $rhett[$row['pcrs_id']] = $row;
            }
        }
        return $rhett;
    }

    /**
     * Retrieves a list of events derived from independent learning sessions in a given curriculum inventory report.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array An assoc. array of assoc. arrays, each item representing an event, keyed off by event id.
     */
    protected function getEventsFromIlmSessions(CurriculumInventoryReportInterface $report)
    {
        $rhett = [];

        $qb = $this->_em->createQueryBuilder();
        $qb->select(
            's.id AS event_id, s.title, sd.description, am.id AS method_id,'
            . 'st.assessment AS is_assessment_method, ao.name AS assessment_option_name, sf.hours'
        )
            ->from('IliosCoreBundle:Session', 's')
            ->join('s.course', 'c')
            ->join('s.ilmSession', 'sf')
            ->join('c.sequenceBlocks', 'sb')
            ->join('sb.report', 'r')
            ->leftJoin('s.sessionDescription', 'sd')
            ->leftJoin('s.sessionType', 'st')
            ->leftJoin('st.aamcMethods', 'am')
            ->leftJoin('st.assessmentOption', 'ao')
            ->where($qb->expr()->eq('s.published', 1))
            ->andWhere($qb->expr()->in('r.id', ':id'))
            ->groupBy('s.id')
            ->addGroupBy('s.title')
            ->addGroupBy('sd.description')
            ->addGroupBy('am.id')
            ->addGroupBy('st.assessment')
            ->setParameter(':id', $report->getId());
        $rows = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);

        foreach ($rows as $row) {
            $row['duration'] = floor($row['hours'] * 60); // convert from hours to minutes
            unset($row['hours']);
            $rhett[$row['event_id']] = $row;
        }
        return $rhett;
    }

    /**
     * Retrieves a list of events (derived from published sessions/offerings and independent learning sessions)
     * in a given curriculum inventory report.
     *
     * @param CurriculumInventoryReportInterface $report
     * @return array An assoc. array of assoc. arrays, each item representing an event, keyed off by event id.
     */
    protected function getEventsFromSessionOfferings(CurriculumInventoryReportInterface $report)
    {
        $rhett = [];
        $sql =<<<EOL
SELECT
  s.session_id AS 'event_id',
  s.title,
  sd.description,
  stxam.method_id,
  st.assessment AS is_assessment_method,
  ao.name AS assessment_option_name,
  sbxs.session_id AS count_offerings_once,
  o.start_date,
  o.end_date
FROM
  `session` s
  JOIN session_type st ON st.session_type_id = s.session_type_id
  JOIN course c ON c.course_id = s.course_id
  JOIN curriculum_inventory_sequence_block sb ON sb.course_id = c.course_id
  LEFT JOIN offering o ON o.session_id = s.session_id
  LEFT JOIN session_description sd ON sd.session_id = s.session_id
  LEFT JOIN session_type_x_aamc_method stxam ON stxam.session_type_id = st.session_type_id
  LEFT JOIN assessment_option ao ON ao.assessment_option_id = st.assessment_option_id
  LEFT JOIN ilm_session_facet sf ON sf.session_id = s.session_id
  LEFT JOIN curriculum_inventory_sequence_block_x_session sbxs 
    ON sbxs.session_id = s.session_id AND sbxs.sequence_block_id = sb.sequence_block_id 
WHERE
  s.published
  AND sf.ilm_session_facet_id IS NULL
  AND sb.report_id = :report_id
ORDER BY
  s.session_id
EOL;
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindValue("report_id", $report->getId());
        $stmt->execute();
        $rows =  $stmt->fetchAll();
        foreach ($rows as $row) {
            $row['duration'] = 0;
            if ($row['start_date']) {
                $startDate = new \Datetime($row['start_date'], new \DateTimeZone('UTC'));
                $endDate = new \DateTime($row['end_date'], new \DateTimeZone('UTC'));
                $duration = floor(($endDate->getTimestamp() - $startDate->getTimestamp()) / 60);
                $row['duration'] = $duration;
            }

            if (!array_key_exists($row['event_id'], $rhett)) {
                $rhett[$row['event_id']] = $row;
            } else {
                if ($row['count_offerings_once']) {
                    if ($rhett[$row['event_id']]['duration'] < $row['duration']) {
                        $rhett[$row['event_id']]['duration'] = $row['duration'];
                    }
                } else {
                    $rhett[$row['event_id']]['duration'] += $row['duration'];
                }
            }
        }

        array_walk($rhett, function (&$row) {
            unset($row['count_offerings_once']);
            unset($row['start_date']);
            unset($row['end_date']);
        });

        $stmt->closeCursor();
        return $rhett;
    }
}

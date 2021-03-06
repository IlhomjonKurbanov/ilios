<?php

namespace Ilios\CoreBundle\Traits;

use Doctrine\ORM\EntityManager;
use Ilios\CoreBundle\Classes\CalendarEvent;
use Ilios\CoreBundle\Classes\UserMaterial;
use Ilios\CoreBundle\Service\UserMaterialFactory;

/**
 * Class CalendarEventRepository
 *
 * Loads CalendarEvents
 */
trait CalendarEventRepository
{
    /**
     * Convert offerings into CalendarEvent() objects
     * @param integer $userId
     * @param array $results
     *
     * @return CalendarEvent[]
     */
    protected function createEventObjectsForOfferings($userId, array $results)
    {
        return array_map(function ($arr) use ($userId) {
            $event = new CalendarEvent();
            $event->name = $arr['title'];
            $event->startDate = $arr['startDate'];
            $event->endDate = $arr['endDate'];
            $event->offering = $arr['id'];
            $event->location = $arr['room'];
            $event->color = $arr['calendarColor'];
            $event->lastModified = max($arr['offeringUpdatedAt'], $arr['sessionUpdatedAt']);
            $event->isPublished = $arr['sessionPublished']  && $arr['coursePublished'];
            $event->isScheduled = $arr['sessionPublishedAsTbd'] || $arr['coursePublishedAsTbd'];
            $event->courseTitle = $arr['courseTitle'];
            $event->sessionTypeTitle = $arr['sessionTypeTitle'];
            $event->courseExternalId = $arr['courseExternalId'];
            $event->sessionDescription = $arr['sessionDescription'];
            $event->session = $arr['sessionId'];
            $event->courseId = $arr['courseId'];
            $event->attireRequired = $arr['attireRequired'];
            $event->equipmentRequired = $arr['equipmentRequired'];
            $event->supplemental = $arr['supplemental'];
            $event->attendanceRequired = $arr['attendanceRequired'];
            return $event;
        }, $results);
    }

    /**
     * Convert IlmSessions into CalendarEvent() objects
     * @param integer $userId
     * @param array $results
     * @return CalendarEvent[]
     */
    protected function createEventObjectsForIlmSessions($userId, array $results)
    {
        return array_map(function ($arr) use ($userId) {
            $event = new CalendarEvent();
            $event->user = $userId;
            $event->name = $arr['title'];
            $event->startDate = $arr['dueDate'];
            $endDate = new \DateTime();
            $endDate->setTimestamp($event->startDate->getTimestamp());
            $event->endDate = $endDate->modify('+15 minutes');
            $event->ilmSession = $arr['id'];
            $event->color = $arr['calendarColor'];
            $event->lastModified = $arr['updatedAt'];
            $event->isPublished = $arr['sessionPublished']  && $arr['coursePublished'];
            $event->isScheduled = $arr['sessionPublishedAsTbd'] || $arr['coursePublishedAsTbd'];
            $event->courseTitle = $arr['courseTitle'];
            $event->sessionTypeTitle = $arr['sessionTypeTitle'];
            $event->courseExternalId = $arr['courseExternalId'];
            $event->sessionDescription = $arr['sessionDescription'];
            $event->session = $arr['sessionId'];
            $event->courseId = $arr['courseId'];
            $event->attireRequired = $arr['attireRequired'];
            $event->equipmentRequired = $arr['equipmentRequired'];
            $event->supplemental = $arr['supplemental'];
            $event->attendanceRequired = $arr['attendanceRequired'];
            return $event;
        }, $results);
    }

    /**
     * Retrieves a list of instructors associated with given offerings.
     *
     * @param array $ids A list of offering ids.
     * @return array A map of instructor lists, keyed off by offering ids.
     */
    protected function getInstructorsForOfferings(array $ids, EntityManager $em)
    {
        if (empty($ids)) {
            return [];
        }

        $qb = $em->createQueryBuilder();
        $qb->select('o.id AS oId, u.id AS userId, u.firstName, u.lastName')
            ->from('IliosCoreBundle:User', 'u');
        $qb->leftJoin('u.instructedOfferings', 'o');
        $qb->where(
            $qb->expr()->in('o.id', ':offerings')
        );
        $qb->setParameter(':offerings', $ids);
        $instructedOfferings = $qb->getQuery()->getArrayResult();


        $qb = $em->createQueryBuilder();
        $qb->select('o.id AS oId, u.id AS userId, u.firstName, u.lastName')
            ->from('IliosCoreBundle:User', 'u');
        $qb->leftJoin('u.instructorGroups', 'ig');
        $qb->leftJoin('ig.offerings', 'o');
        $qb->where(
            $qb->expr()->in('o.id', ':offerings')
        );
        $qb->setParameter(':offerings', $ids);
        $groupOfferings = $qb->getQuery()->getArrayResult();

        $results = array_merge($instructedOfferings, $groupOfferings);

        $offeringInstructors = [];
        foreach ($results as $result) {
            if (! array_key_exists($result['oId'], $offeringInstructors)) {
                $offeringInstructors[$result['oId']] = [];
            }
            $offeringInstructors[$result['oId']][$result['userId']] = $result['firstName'] . ' ' . $result['lastName'];
        }
        return $offeringInstructors;
    }

    /**
     * Retrieves a list of instructors associated with given ILM sessions.
     *
     * @param array $ids A list of ILM session ids.
     * @param EntityManager $em
     * @return array A map of instructor lists, keyed off by ILM sessions ids.
     */
    protected function getInstructorsForIlmSessions(array $ids, EntityManager $em)
    {
        if (empty($ids)) {
            return [];
        }

        $qb = $em->createQueryBuilder();
        $qb->select('ilm.id AS ilmId, u.id AS userId, u.firstName, u.lastName')
            ->from('IliosCoreBundle:User', 'u');
        $qb->leftJoin('u.instructorIlmSessions', 'ilm');
        $qb->where(
            $qb->expr()->in('ilm.id', ':ilms')
        );
        $qb->setParameter(':ilms', $ids);
        $instructedIlms = $qb->getQuery()->getArrayResult();

        $qb = $em->createQueryBuilder();
        $qb->select('ilm.id AS ilmId, u.id AS userId, u.firstName, u.lastName')
            ->from('IliosCoreBundle:User', 'u');
        $qb->leftJoin('u.instructorGroups', 'ig');
        $qb->leftJoin('ig.ilmSessions', 'ilm');
        $qb->where(
            $qb->expr()->in('ilm.id', ':ilms')
        );
        $qb->setParameter(':ilms', $ids);
        $groupIlms = $qb->getQuery()->getArrayResult();

        $results = array_merge($instructedIlms, $groupIlms);

        $ilmInstructors = [];
        foreach ($results as $result) {
            if (! array_key_exists($result['ilmId'], $ilmInstructors)) {
                $ilmInstructors[$result['ilmId']] = [];
            }
            $ilmInstructors[$result['ilmId']][$result['userId']] = $result['firstName'] . ' ' . $result['lastName'];
        }
        return $ilmInstructors;
    }

    /**
     * Adds instructors to a given list of events.
     * @param array $events A list of events
     * @param EntityManager $em
     * @return array The events list with instructors added.
     */
    public function attachInstructorsToEvents(array $events, EntityManager $em)
    {
        $offeringIds = array_map(function ($event) {
            return $event->offering;
        }, array_filter($events, function ($event) {
            return $event->offering;
        }));

        $ilmIds = array_map(function ($event) {
            return $event->ilmSession;
        }, array_filter($events, function ($event) {
            return $event->ilmSession;
        }));

        // array-filtering throws off the array index.
        // set this right again.
        $events = array_values($events);

        $offeringInstructors = $this->getInstructorsForOfferings($offeringIds, $em);
        $ilmInstructors = $this->getInstructorsForIlmSessions($ilmIds, $em);

        for ($i = 0, $n = count($events); $i < $n; $i++) {
            if ($events[$i]->offering) { // event maps to offering
                if (array_key_exists($events[$i]->offering, $offeringInstructors)) {
                    $events[$i]->instructors = array_values($offeringInstructors[$events[$i]->offering]);
                }
            } elseif ($events[$i]->ilmSession) { // event maps to ILM session
                if (array_key_exists($events[$i]->ilmSession, $ilmInstructors)) {
                    $events[$i]->instructors = array_values($ilmInstructors[$events[$i]->ilmSession]);
                }
            }
        }
        return $events;
    }

    /**
     * Finds and adds learning materials to a given list of calendar events.
     *
     * @param CalendarEvent[] $events
     * @param UserMaterialFactory $factory
     * @param EntityManager $em
     * @return CalendarEvent[]
     */
    public function attachMaterialsToEvents(array $events, UserMaterialFactory $factory, EntityManager $em)
    {
        $sessionIds = array_map(function (CalendarEvent $event) {
            return $event->session;
        }, $events);

        $sessionIds = array_values(array_unique($sessionIds));

        $sessionMaterials = $this->getLearningMaterialsForSessions($sessionIds, $em);

        $sessionUserMaterials = array_map(function (array $arr) use ($factory) {
            return $factory->create($arr);
        }, $sessionMaterials);

        $courseMaterials = $this->getLearningMaterialsForCourses($sessionIds, $em);

        $courseUserMaterials = array_map(function (array $arr) use ($factory) {
            return $factory->create($arr);
        }, $courseMaterials);



        //sort materials by id for consistency
        $sortFn = function (UserMaterial $a, UserMaterial $b) {
            return $a->id - $b->id;
        };

        usort($sessionUserMaterials, $sortFn);
        usort($courseUserMaterials, $sortFn);

        // group materials by session or course
        $groupedSessionLms = [];
        $groupedCourseLms = [];
        for ($i = 0, $n = count($sessionUserMaterials); $i < $n; $i++) {
            $lm = $sessionUserMaterials[$i];
            $id = $lm->session;
            if (! array_key_exists($id, $groupedSessionLms)) {
                $groupedSessionLms[$id] = [];
            }
            $groupedSessionLms[$id][] = $lm;
        }
        for ($i = 0, $n = count($courseUserMaterials); $i < $n; $i++) {
            $lm = $courseUserMaterials[$i];
            $id = $lm->course;
            if (! array_key_exists($id, $groupedCourseLms)) {
                $groupedCourseLms[$id] = [];
            }
            $groupedCourseLms[$id][] = $lm;
        }

        for ($i =0, $n = count($events); $i < $n; $i++) {
            $event = $events[$i];
            $sessionId = $event->session;
            $courseId = $event->courseId;
            $sessionLms = array_key_exists($sessionId, $groupedSessionLms) ? $groupedSessionLms[$sessionId] : [];
            $courseLms = array_key_exists($courseId, $groupedCourseLms) ? $groupedCourseLms[$courseId] : [];
            $lms = array_merge($sessionLms, $courseLms);
            $event->learningMaterials = $lms;
        }
        return $events;
    }

    /**
     * Get a set of learning materials based on session
     *
     * @param array $sessionIds
     *
     * @param EntityManager $em
     * @return array
     */
    protected function getLearningMaterialsForSessions(
        array $sessionIds,
        EntityManager $em
    ) {

        $qb = $em->createQueryBuilder();
        $what = 's.title as sessionTitle, s.id as sessionId, ' .
            'c.id as courseId, c.title as courseTitle, ' .
            'slm.id as slmId, slm.position, slm.notes, slm.required, slm.publicNotes, slm.startDate, slm.endDate, ' .
            'lm.id, lm.title, lm.description, lm.originalAuthor, lm.token, ' .
            'lm.citation, lm.link, lm.filename, lm.filesize, lm.mimetype, lms.id AS status';
        $qb->select($what)->from('IliosCoreBundle:Session', 's');
        $qb->join('s.learningMaterials', 'slm');
        $qb->join('slm.learningMaterial', 'lm');
        $qb->join('lm.status', 'lms');
        $qb->join('s.course', 'c');

        $qb->andWhere($qb->expr()->in('s.id', ':sessions'));
        $qb->andWhere($qb->expr()->eq('s.published', 1));
        $qb->andWhere($qb->expr()->eq('s.publishedAsTbd', 0));
        $qb->andWhere($qb->expr()->eq('c.published', 1));
        $qb->andWhere($qb->expr()->eq('c.publishedAsTbd', 0));
        $qb->setParameter(':sessions', $sessionIds);
        $qb->distinct();

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Get a set of course learning materials based on sessionIds
     *
     * @param array $sessionIds
     *
     * @param EntityManager $em
     * @return array
     */
    protected function getLearningMaterialsForCourses(
        array $sessionIds,
        EntityManager $em
    ) {

        $qb = $em->createQueryBuilder();
        $what = 'c.title as courseTitle, c.id as courseId, c.startDate as firstOfferingDate, ' .
            'clm.id as clmId, clm.position, clm.notes, clm.required, clm.publicNotes, clm.startDate, clm.endDate, ' .
            'lm.id, lm.title, lm.description, lm.originalAuthor, lm.token, ' .
            'lm.citation, lm.link, lm.filename, lm.filesize, lm.mimetype, lms.id AS status';
        $qb->select($what)->from('IliosCoreBundle:Session', 's');
        $qb->join('s.course', 'c');
        $qb->join('c.learningMaterials', 'clm');
        $qb->join('clm.learningMaterial', 'lm');
        $qb->join('lm.status', 'lms');


        $qb->andWhere($qb->expr()->in('s.id', ':sessions'));
        $qb->andWhere($qb->expr()->eq('c.published', 1));
        $qb->andWhere($qb->expr()->eq('c.publishedAsTbd', 0));
        $qb->setParameter(':sessions', $sessionIds);
        $qb->distinct();

        return $qb->getQuery()->getArrayResult();
    }
}

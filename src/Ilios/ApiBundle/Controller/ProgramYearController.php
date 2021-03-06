<?php

namespace Ilios\ApiBundle\Controller;

use Ilios\AuthenticationBundle\RelationshipVoter\AbstractVoter;
use Ilios\CoreBundle\Entity\CohortInterface;
use Ilios\CoreBundle\Entity\Manager\BaseManager;
use Ilios\CoreBundle\Entity\Manager\ProgramYearStewardManager;
use Ilios\CoreBundle\Entity\ProgramYearInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ProgramYearController
 * When program years are created cohorts
 * must be created at the same time
 */
class ProgramYearController extends ApiController
{
    /**
     * Create cohort to match the new program year
     * @inheritdoc
     */
    public function postAction($version, $object, Request $request)
    {
        $manager = $this->getManager($object);
        $class = $manager->getClass() . '[]';

        $json = $this->extractJsonFromRequest($request, $object, 'POST');
        $serializer = $this->getSerializer();
        $entities = $serializer->deserialize($json, $class, 'json');
        $this->validateAndAuthorizeEntities($entities, AbstractVoter::CREATE);

        foreach ($entities as $entity) {
            $manager->update($entity, false);
            $this->createCohort($entity);
        }
        $manager->flush();

        return $this->createResponse($this->getPluralResponseKey($object), $entities, Response::HTTP_CREATED);
    }

    /**
     * Allow program years to be unlocked if necessary
     * and add a cohort if one does not already exist
     * @inheritdoc
     */
    public function putAction($version, $object, $id, Request $request)
    {
        /** @var BaseManager $manager */
        $manager = $this->getManager($object);
        /** @var ProgramYearInterface $entity */
        $entity = $manager->findOneBy(['id'=> $id]);
        $data = $this->extractPutDataFromRequest($request, $object);

        if ($entity) {
            if (!$entity->isArchived() && $data->archived) {
                return $this->archiveProgramYear($object, $manager, $entity);
            }
            if ($entity->isLocked() && !$data->locked) {
                return $this->unlockProgramYear($object, $manager, $entity);
            }
            if (!$entity->isLocked() && $data->locked) {
                return $this->lockProgramYear($object, $manager, $entity);
            }
            $code = Response::HTTP_OK;
            $permission = AbstractVoter::EDIT;
        } else {
            $entity = $manager->create();
            $code = Response::HTTP_CREATED;
            $permission = AbstractVoter::CREATE;
        }
        $json = json_encode($data);
        $serializer = $this->getSerializer();
        $serializer->deserialize($json, get_class($entity), 'json', ['object_to_populate' => $entity]);
        $this->validateAndAuthorizeEntities([$entity], $permission);

        if (empty($entity->getCohort())) {
            $this->createCohort($entity);
        }

        $manager->update($entity, true, false);

        return $this->createResponse($this->getSingularResponseKey($object), $entity, $code);
    }

    /**
     * Creates a new cohort for a new program year.
     * @param ProgramYearInterface $programYear
     */
    protected function createCohort(ProgramYearInterface $programYear)
    {
        $cohortManager = $this->getManager('cohorts');

        $program = $programYear->getProgram();
        $graduationYear = $programYear->getStartYear() + $program->getDuration();

        /* @var CohortInterface $cohort */
        $cohort = $cohortManager->create();
        $cohort->setTitle("Class of ${graduationYear}");
        $cohort->setProgramYear($programYear);
        $programYear->setCohort($cohort);

        $cohortManager->update($cohort, false, false);
    }

    /**
     * @param string $object
     * @param BaseManager $manager
     * @param ProgramYearInterface $entity
     * @return Response
     */
    protected function archiveProgramYear($object, BaseManager $manager, ProgramYearInterface $entity)
    {
        if (! $this->authorizationChecker->isGranted(AbstractVoter::ARCHIVE, $entity)) {
            throw $this->createAccessDeniedException('Unauthorized access!');
        }
        $entity->setArchived(true);
        $manager->update($entity, true, false);

        return $this->createResponse($this->getSingularResponseKey($object), $entity, Response::HTTP_OK);
    }

    /**
     * @param string $object
     * @param BaseManager $manager
     * @param ProgramYearInterface $entity
     * @return Response
     */
    protected function lockProgramYear($object, BaseManager $manager, ProgramYearInterface $entity)
    {
        if (! $this->authorizationChecker->isGranted(AbstractVoter::LOCK, $entity)) {
            throw $this->createAccessDeniedException('Unauthorized access!');
        }
        $entity->setLocked(true);
        $manager->update($entity, true, false);

        return $this->createResponse($this->getSingularResponseKey($object), $entity, Response::HTTP_OK);
    }

    /**
     * @param string $object
     * @param BaseManager $manager
     * @param ProgramYearInterface $entity
     * @return Response
     */
    protected function unlockProgramYear($object, BaseManager $manager, ProgramYearInterface $entity)
    {
        if (!$this->authorizationChecker->isGranted(AbstractVoter::UNLOCK, $entity)) {
            throw $this->createAccessDeniedException('Unauthorized access!');
        }
        $entity->setLocked(false);
        $manager->update($entity, true, false);

        return $this->createResponse($this->getSingularResponseKey($object), $entity, Response::HTTP_OK);
    }
}

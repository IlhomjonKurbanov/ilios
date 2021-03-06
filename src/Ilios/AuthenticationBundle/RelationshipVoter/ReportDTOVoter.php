<?php

namespace Ilios\AuthenticationBundle\RelationshipVoter;

use Ilios\AuthenticationBundle\Classes\SessionUserInterface;
use Ilios\CoreBundle\Entity\DTO\ReportDTO;
use Ilios\CoreBundle\Entity\ReportInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Class Report
 */
class ReportDTOVoter extends AbstractVoter
{
    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject)
    {
        return $subject instanceof ReportDTO && self::VIEW === $attribute;
    }

    /**
     * @param string $attribute
     * @param ReportDTO $report
     * @param TokenInterface $token
     * @return bool
     */
    protected function voteOnAttribute($attribute, $report, TokenInterface $token)
    {
        $user = $token->getUser();
        if (!$user instanceof SessionUserInterface) {
            return false;
        }

        if ($user->isRoot()) {
            return true;
        }

        return $user->getId() === $report->user;
    }
}

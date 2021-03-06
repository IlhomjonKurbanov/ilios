<?php

namespace Tests\AuthenticationBundle\RelationshipVoter;

use Ilios\AuthenticationBundle\RelationshipVoter\AbstractVoter;
use Ilios\AuthenticationBundle\RelationshipVoter\ReportDTOVoter;
use Ilios\AuthenticationBundle\Service\PermissionChecker;
use Ilios\CoreBundle\Entity\DTO\ReportDTO;
use Mockery as m;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Class ReportDTOVoterTest
 * @package Tests\AuthenticationBundle\RelationshipVoter
 */
class ReportDTOVoterTest extends AbstractBase
{
    /**
     * @inheritdoc
     */
    public function setup()
    {
        $this->permissionChecker = m::mock(PermissionChecker::class);
        $this->voter = new ReportDTOVoter($this->permissionChecker);
    }

    /**
     * @covers ReportDTOVoter::voteOnAttribute()
     */
    public function testCanViewDTO()
    {
        $userId = 1;
        $token = $this->createMockTokenWithNonRootSessionUser();
        $token->getUser()->shouldReceive('getId')->andReturn($userId);
        $dto = m::mock(ReportDTO::class);
        $dto->user = $userId;
        $response = $this->voter->vote($token, $dto, [AbstractVoter::VIEW]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $response, "DTO View allowed");
    }

    /**
     * @covers ReportDTOVoter::voteOnAttribute()
     */
    public function testRootCanViewDTO()
    {
        $token = $this->createMockTokenWithRootSessionUser();
        $dto = m::mock(ReportDTO::class);
        $response = $this->voter->vote($token, $dto, [AbstractVoter::VIEW]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $response, "DTO View allowed");
    }

    /**
     * @covers ReportDTOVoter::voteOnAttribute()
     */
    public function testCanNotViewDTO()
    {
        $userId = 1;
        $reportOwnerId = 2;
        $token = $this->createMockTokenWithNonRootSessionUser();
        $token->getUser()->shouldReceive('getId')->andReturn($userId);
        $dto = m::mock(ReportDTO::class);
        $dto->user = $reportOwnerId;
        $response = $this->voter->vote($token, $dto, [AbstractVoter::VIEW]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $response, "DTO View allowed");
    }
}

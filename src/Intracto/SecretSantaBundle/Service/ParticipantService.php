<?php

namespace Intracto\SecretSantaBundle\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Intracto\SecretSantaBundle\Entity\EmailAddress;
use Intracto\SecretSantaBundle\Entity\Participant;
use Intracto\SecretSantaBundle\Entity\Party;
use Intracto\SecretSantaBundle\Validator\ParticipantIsNotBlacklisted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ParticipantService
{
    /**
     * @var EntityManager
     */
    public $em;

    /**
     * @var ParticipantShuffler
     */
    public $participantShuffler;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    public function __construct(EntityManagerInterface $em, ParticipantShuffler $participantShuffler, ValidatorInterface $validator)
    {
        $this->em = $em;
        $this->participantShuffler = $participantShuffler;
        $this->validator = $validator;
    }

    /**
     * Shuffles all participants for party and save result to each participant.
     *
     * @param Party $party
     *
     * @return bool
     */
    public function shuffleParticipants(Party $party)
    {
        // Validator should already have shuffled it.
        if (!$shuffled = $this->participantShuffler->shuffleParticipants($party)) {
            return false;
        }

        foreach ($party->getParticipants() as $key => $participant) {
            $match = $shuffled[$key];
            $participant->setAssignedParticipant($match)
                ->setUrl(base_convert(sha1(uniqid(mt_rand(), true)), 16, 36));

            $this->em->persist($participant);
        }

        $this->em->flush();
    }

    public function validateEmail(string $email) : bool
    {
        $emailAddress = new EmailAddress($email);

        $emailAddressErrors = $this->validator->validate($emailAddress);
        $blacklisted = $this->validator->validate($emailAddress, new ParticipantIsNotBlacklisted());
        if ((count($emailAddressErrors) > 0 || count($blacklisted)) > 0) {
            return false;
        }

        return true;
    }

    public function editParticipant(Participant $participant, string $name, string $email)
    {
        $participant->setEmail($email);
        $participant->setName($name);

        $this->em->persist($participant);
        $this->em->flush();
    }

    public function logFirstAccess(Participant $participant, string $ip)
    {
        if ($participant->getViewdate() === null) {
            $participant->setViewdate(new \DateTime());

            $this->em->flush($participant);
        }

        if ($participant->getIp() === null) {
            $participant->setIp($ip);

            $this->em->flush($participant);
        }
    }
}

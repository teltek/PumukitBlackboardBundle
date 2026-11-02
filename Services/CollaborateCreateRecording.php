<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\BlackboardBundle\ValueObject\CollaborateRecording;

class CollaborateCreateRecording
{
    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {}

    public function create(CollaborateRecording $collaborateRecording): ?\Pumukit\BlackboardBundle\Document\CollaborateRecording
    {
        if ($recording = $this->wasCreated($collaborateRecording->id())) {
            return $recording;
        }

        $recording = \Pumukit\BlackboardBundle\Document\CollaborateRecording::create($collaborateRecording);
        $this->documentManager->persist($recording);
        $this->documentManager->flush();

        return $recording;
    }

    public function alreadyExists(string $recordingId): bool
    {
        return null !== $this->wasCreated($recordingId);
    }

    private function wasCreated(string $recordingId): ?\Pumukit\BlackboardBundle\Document\CollaborateRecording
    {
        return $this->documentManager->getRepository(\Pumukit\BlackboardBundle\Document\CollaborateRecording::class)->findOneBy([
            'recording' => $recordingId,
        ]);
    }
}

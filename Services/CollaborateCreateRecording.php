<?php

namespace Pumukit\BlackboardBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\BlackboardBundle\ValueObject\CollaborateRecording;

class CollaborateCreateRecording
{
    private DocumentManager $documentManager;

    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    public function create(CollaborateRecording $collaborateRecording): ?\Pumukit\BlackboardBundle\Document\CollaborateRecording
    {
        if ($this->wasCreated($collaborateRecording->id())) {
            return null;
        }

        $recording = \Pumukit\BlackboardBundle\Document\CollaborateRecording::create($collaborateRecording);
        $this->documentManager->persist($recording);
        $this->documentManager->flush();

        return $recording;
    }

    private function wasCreated(string $recordingId): bool
    {
        $recording = $this->documentManager->getRepository(\Pumukit\BlackboardBundle\Document\CollaborateRecording::class)->findBy([
            'recordingId' => $recordingId,
        ]);

        return (bool) $recording;
    }
}

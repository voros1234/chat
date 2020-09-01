<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Participant;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
/**
 * @Route("/conversations", name="conversations.")
 */
class ConversationController extends AbstractController
{
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var ConversationRepository
     */
    private $conversationRepository;

    public function __construct(UserRepository $userRepository,
                                EntityManagerInterface $entityManager,
                                ConversationRepository $conversationRepository)
    {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->conversationRepository = $conversationRepository;
    }

    /**
     * @Route("/{id}", name="getConversations")
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
    public function index(Request $request, int $id)
    {
        $otherUser = $this->userRepository->find($id);

        if(is_null($otherUser)){
            throw new Exception("Nincs ilyen felhasználó");
        }

        // Magunkkal nem tudunk beszélgetést csinálni
        if($otherUser->getId() === $this->getUser()->getId()){
            throw new Exception("Magunkkal nem tudunk beszélgetést indítani");
        }

        // Megnézzük hogy ezzel a felhasználóval van-e már beszélgetésünk
        $conversation = $this->conversationRepository->findConversationByParticipants(
            $otherUser->getId(),
            $this->getUser()->getId()
        );

        if(count($conversation)){
            throw new Exception("Már van ilyen beszélgetés");
        }

        $conversation = new Conversation();

        $participant = new Participant();
        $participant->setUser($this->getUser());
        $participant->setConversation($conversation);

        $otherParticipant = new Participant();
        $otherParticipant->setUser($otherUser);
        $otherParticipant->setConversation($conversation);

        $this->entityManager->getConnection()->beginTransaction();
        try{
            $this->entityManager->persist($conversation);
            $this->entityManager->persist($participant);
            $this->entityManager->persist($otherParticipant);

            $this->entityManager->flush();
            $this->entityManager->commit();
        }catch (\Exception $e){
            $this->entityManager->rollback();
            throw $e;
        }

        return $this->json([
            'id' => $conversation->getId()
        ], Response::HTTP_CREATED, [], []);
    }
}

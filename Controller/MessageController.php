<?php

namespace FOS\MessageBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use FOS\MessageBundle\Provider\ProviderInterface;

class MessageController extends ContainerAware
{
    /**
     * Displays the authenticated participant inbox
     *
     * @param Request $request
     *
     * @return Response
     */
    public function inboxAction(Request $request)
    {
        //$threads = $this->getProvider()->getInboxThreads();

        $paginator  = $this->container->get('knp_paginator');
        $threads = $paginator->paginate(
            $this->container->get('fos_message.thread_manager')->getParticipantInboxThreadsQueryBuilder(
                $this->container->get('fos_message.participant_provider')->getAuthenticatedParticipant()
            )->getQuery(),
            $request->query->getInt('page', 1)/*page number*/,
            15/*limit per page*/
        );

        return $this->container->get('templating')->renderResponse('FOSMessageBundle:Message:inbox.html.twig', array(
            'threads' => $threads
        ));
    }

    /**
     * Displays the authenticated participant messages sent
     *
     * @param Request $request
     *
     * @return Response
     */
    public function sentAction(Request $request)
    {
        //$threads = $this->getProvider()->getSentThreads();

        $paginator  = $this->container->get('knp_paginator');
        $threads = $paginator->paginate(
            $this->container->get('fos_message.thread_manager')->getParticipantSentThreadsQueryBuilder(
                $this->container->get('fos_message.participant_provider')->getAuthenticatedParticipant()
            )->getQuery(),
            $request->query->getInt('page', 1)/*page number*/,
            15/*limit per page*/
        );

        return $this->container->get('templating')->renderResponse('FOSMessageBundle:Message:sent.html.twig', array(
            'threads' => $threads
        ));
    }

    /**
     * Displays the authenticated participant deleted threads
     *
     * @param Request $request
     *
     * @return Response
     */
    public function deletedAction(Request $request)
    {
        //$threads = $this->getProvider()->getDeletedThreads();

        $paginator  = $this->container->get('knp_paginator');
        $threads = $paginator->paginate(
            $this->container->get('fos_message.thread_manager')->getParticipantDeletedThreadsQueryBuilder(
                $this->container->get('fos_message.participant_provider')->getAuthenticatedParticipant()
            )->getQuery(),
            $request->query->getInt('page', 1)/*page number*/,
            15/*limit per page*/
        );

        return $this->container->get('templating')->renderResponse('FOSMessageBundle:Message:deleted.html.twig', array(
            'threads' => $threads
        ));
    }

    /**
     * Displays a thread, also allows to reply to it
     *
     * @param string $threadId the thread id
     * 
     * @return Response
     */
    public function threadAction($threadId)
    {
        $thread = $this->getProvider()->getThread($threadId);
        $form = $this->container->get('fos_message.reply_form.factory')->create($thread);
        $formHandler = $this->container->get('fos_message.reply_form.handler');

        if ($message = $formHandler->process($form)) {
            return new RedirectResponse($this->container->get('router')->generate('fos_message_thread_view', array(
                'threadId' => $message->getThread()->getId()
            )));
        }

        return $this->container->get('templating')->renderResponse('FOSMessageBundle:Message:thread.html.twig', array(
            'form' => $form->createView(),
            'thread' => $thread
        ));
    }

    /**
     * Create a new message thread
     *
     * @return Response
     */
    public function newThreadAction()
    {
        $form = $this->container->get('fos_message.new_thread_form.factory')->create();
        $formHandler = $this->container->get('fos_message.new_thread_form.handler');

        if ($message = $formHandler->process($form)) {
            return new RedirectResponse($this->container->get('router')->generate('fos_message_thread_view', array(
                'threadId' => $message->getThread()->getId()
            )));
        }

        return $this->container->get('templating')->renderResponse('FOSMessageBundle:Message:newThread.html.twig', array(
            'form' => $form->createView(),
            'data' => $form->getData()
        ));
    }

    /**
     * Deletes a thread
     * 
     * @param Request $request
     * 
     * @return RedirectResponse
     */
    public function deleteAction(Request $request)
    {
        $threads = $request->request->get("threadId");

        $threadsArray = explode(",", $threads);

        foreach ($threadsArray as $threadId) {
            $thread = $this->getProvider()->getThread($threadId);
            $this->container->get('fos_message.deleter')->markAsDeleted($thread);
            $this->container->get('fos_message.thread_manager')->saveThread($thread);
        }

        return new RedirectResponse($this->container->get('router')->generate('fos_message_inbox'));
    }
    
    /**
     * Undeletes a thread
     *
     * @param Request $request
     * 
     * @return RedirectResponse
     */
    public function undeleteAction(Request $request)
    {
        $threads = $request->request->get("threadId");

        $threadsArray = explode(",", $threads);

        foreach ($threadsArray as $threadId) {
            $thread = $this->getProvider()->getThread($threadId);
            $this->container->get('fos_message.deleter')->markAsUndeleted($thread);
            $this->container->get('fos_message.thread_manager')->saveThread($thread);
        }

        return new RedirectResponse($this->container->get('router')->generate('fos_message_inbox'));
    }

    /**
     * Searches for messages in the inbox and sentbox
     *
     * @return Response
     */
    public function searchAction()
    {
        $query = $this->container->get('fos_message.search_query_factory')->createFromRequest();
        $threads = $this->container->get('fos_message.search_finder')->find($query);

        return $this->container->get('templating')->renderResponse('FOSMessageBundle:Message:search.html.twig', array(
            'query' => $query,
            'threads' => $threads
        ));
    }

    /**
     * Gets the provider service
     *
     * @return ProviderInterface
     */
    protected function getProvider()
    {
        return $this->container->get('fos_message.provider');
    }
}

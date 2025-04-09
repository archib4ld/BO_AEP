<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\RegistrationFormType;
use App\Security\LoginFormAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bundle\SecurityBundle\Security;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $authenticator,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        Security $security
    ): Response {
        // Si l'utilisateur est déjà connecté, rediriger vers la page d'accueil
        if ($security->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new Utilisateur();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérification du captcha
            $captcha = $request->getSession()->get('captcha');
            $submittedCaptcha = $form->get('captcha')->getData();

            if ($captcha !== $submittedCaptcha) {
                $this->addFlash('error', 'Le code de vérification est incorrect.');
                $request->getSession()->set('captcha', $this->generateCaptcha());
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                    'captcha' => $request->getSession()->get('captcha')
                ]);
            }

            // Encodage du mot de passe
            $user->setPwdCrypt(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Activation du compte
            $user->setCompteActif(true);

            // Enregistrement de l'utilisateur
            $entityManager->persist($user);
            $entityManager->flush();

            // Envoi de l'email de confirmation
            $this->sendConfirmationEmail($user, $mailer);

            // Authentification automatique
            return $userAuthenticator->authenticateUser(
                $user,
                $authenticator,
                $request
            );
        }

        // Génération d'un nouveau captcha si nécessaire
        if (!$request->getSession()->has('captcha')) {
            $request->getSession()->set('captcha', $this->generateCaptcha());
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            'captcha' => $request->getSession()->get('captcha')
        ]);
    }

    private function generateCaptcha(): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $captcha = '';
        for ($i = 0; $i < 6; $i++) {
            $captcha .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $captcha;
    }

    private function sendConfirmationEmail(Utilisateur $user, MailerInterface $mailer): void
    {
        $email = (new Email())
            ->from('noreply@example.com')
            ->to($user->getEmail())
            ->subject('Bienvenue sur notre plateforme !')
            ->html($this->renderView('emails/registration.html.twig', [
                'user' => $user
            ]));

        $mailer->send($email);
    }
} 
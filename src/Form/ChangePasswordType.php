<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * New password (optionally confirming the current one). Unmapped: the controller hashes it.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['require_current']) {
            $builder->add('currentPassword', PasswordType::class, [
                'label' => 'password.current',
                'constraints' => [new Assert\NotBlank(), new UserPassword(message: 'password.current_wrong')],
                'attr' => ['autocomplete' => 'current-password'],
            ]);
        }

        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'password.mismatch',
            'first_options' => ['label' => 'password.new', 'attr' => ['autocomplete' => 'new-password'], 'help' => 'password.rules'],
            'second_options' => ['label' => 'password.repeat', 'attr' => ['autocomplete' => 'new-password']],
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Length(min: 12, max: 4096),
                new Assert\NotCompromisedPassword(skipOnError: true),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['require_current' => true]);
        $resolver->setAllowedTypes('require_current', 'bool');
    }
}

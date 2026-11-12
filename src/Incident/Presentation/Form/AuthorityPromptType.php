<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

/** @extends AbstractType<null> */
final class AuthorityPromptType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reported', SubmitType::class, ['label' => 'authority_prompt.reported', 'attr' => ['class' => 'btn-primary']])
            ->add('later', SubmitType::class, ['label' => 'authority_prompt.later', 'attr' => ['class' => 'btn-secondary']]);
    }
}

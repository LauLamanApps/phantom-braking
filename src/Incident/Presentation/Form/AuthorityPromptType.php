<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Form;

use App\Vehicle\Domain\ValueObject\Authority;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<null> */
final class AuthorityPromptType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Authority $authority */
        $authority = $options['authority'];

        $builder
            ->add('reported', SubmitType::class, ['label' => $authority->translationKey().'.reported', 'attr' => ['class' => 'btn-primary']])
            ->add('later', SubmitType::class, ['label' => 'authority_prompt.later', 'attr' => ['class' => 'btn-secondary']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('authority')->setAllowedTypes('authority', Authority::class);
    }
}

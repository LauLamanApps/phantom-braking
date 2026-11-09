<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Form;

use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\ValueObject\ActiveAssistSystem;
use App\Incident\Domain\ValueObject\BrakeSeverity;
use App\Incident\Domain\ValueObject\Consequence;
use App\Incident\Domain\ValueObject\DealerResponse;
use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\LightCondition;
use App\Incident\Domain\ValueObject\Recurrence;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;
use App\Incident\Domain\ValueObject\SuspectedTrigger;
use App\Incident\Domain\ValueObject\Weather;
use App\SharedKernel\Domain\ValueObject\TranslatableEnum;
use App\Vehicle\Domain\ValueObject\Country;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\Dropzone\Form\DropzoneType;

/** @extends AbstractType<ReportIncidentData> */
final class ReportIncidentType extends AbstractType
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $enum = static fn (string $class, array $extra = []): array => [
            'class' => $class,
            'choice_label' => static fn (TranslatableEnum $case): string => $case->translationKey(),
            'placeholder' => 'form.choose',
        ] + $extra;

        $builder
            // Vehicle
            ->add('country', EnumType::class, $enum(Country::class, ['label' => 'report.country', 'placeholder' => false, 'choices' => $options['countries']]))
            ->add('licensePlate', TextType::class, [
                'label' => 'report.license_plate',
                'help' => 'report.license_plate.help',
                'attr' => ['autocomplete' => 'off', 'autocapitalize' => 'characters', 'placeholder' => 'AB-123-C', 'class' => 'uppercase tracking-widest font-mono'],
            ])
            // When & where
            ->add('occurredAt', DateTimeType::class, [
                'label' => 'report.occurred_at',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('incidentCountry', ChoiceType::class, [
                'label' => 'report.incident_country',
                'help' => 'report.incident_country.help',
                'choices' => $this->europeanCountries($options['locale']),
                'preferred_choices' => ['NL', 'BE', 'DE', 'FR', 'LU'],
                'choice_translation_domain' => false,
                'placeholder' => false,
            ])
            ->add('roadType', EnumType::class, $enum(RoadType::class, ['label' => 'report.road_type']))
            ->add('roadSituation', EnumType::class, $enum(RoadSituation::class, ['label' => 'report.road_situation', 'help' => 'report.road_situation.help']))
            ->add('roadNumber', TextType::class, ['label' => 'report.road_number', 'required' => false, 'attr' => ['placeholder' => 'A15, N325, …']])
            ->add('direction', TextType::class, ['label' => 'report.direction', 'required' => false, 'attr' => ['placeholder' => 'report.direction.placeholder']])
            ->add('speedLimit', IntegerType::class, ['label' => 'report.speed_limit', 'required' => false, 'attr' => ['min' => 15, 'max' => 200, 'step' => 5]])
            ->add('latitude', HiddenType::class, ['required' => false, 'attr' => ['data-location-picker-target' => 'latitude']])
            ->add('longitude', HiddenType::class, ['required' => false, 'attr' => ['data-location-picker-target' => 'longitude']])
            // What happened
            ->add('severity', EnumType::class, $enum(BrakeSeverity::class, ['label' => 'report.severity', 'expanded' => true, 'placeholder' => false]))
            ->add('speedBefore', IntegerType::class, ['label' => 'report.speed_before', 'attr' => ['min' => 0, 'max' => 250, 'inputmode' => 'numeric']])
            ->add('speedAfter', IntegerType::class, ['label' => 'report.speed_after', 'required' => false, 'help' => 'report.speed_after.help', 'attr' => ['min' => 0, 'max' => 250, 'inputmode' => 'numeric']])
            ->add('trigger', EnumType::class, $enum(SuspectedTrigger::class, ['label' => 'report.trigger']))
            ->add('assistSystem', EnumType::class, $enum(ActiveAssistSystem::class, [
                'label' => 'report.assist',
                'help' => 'report.assist.help',
                // Make-specific systems start hidden; make_options_controller.js shows them once the plate lookup finds that make.
                'choice_attr' => static fn (ActiveAssistSystem $case): array => null === $case->requiredMake() ? [] : ['data-make' => $case->requiredMake(), 'hidden' => true, 'disabled' => true],
                'attr' => ['data-controller' => 'make-options', 'data-action' => 'vehicle-lookup:changed@window->make-options#filter'],
            ]))
            ->add('warningShown', ChoiceType::class, [
                'label' => 'report.warning_shown',
                'choices' => ['form.yes' => true, 'form.no' => false],
                'placeholder' => 'form.unknown',
                'required' => false,
            ])
            ->add('consequence', EnumType::class, $enum(Consequence::class, ['label' => 'report.consequence']))
            // Conditions
            ->add('weather', EnumType::class, $enum(Weather::class, ['label' => 'report.weather']))
            ->add('light', EnumType::class, $enum(LightCondition::class, ['label' => 'report.light']))
            // History & follow-up
            ->add('recurrence', EnumType::class, $enum(Recurrence::class, ['label' => 'report.recurrence']))
            ->add('dealerResponse', EnumType::class, $enum(DealerResponse::class, ['label' => 'report.dealer', 'placeholder' => false]))
            ->add('reportedToAuthority', CheckboxType::class, ['label' => 'report.reported_to_authority', 'required' => false])
            ->add('windscreenReplacedRecently', ChoiceType::class, [
                'label' => 'report.windscreen',
                'help' => 'report.windscreen.help',
                'choices' => ['form.yes' => true, 'form.no' => false],
                'placeholder' => 'form.unknown',
                'required' => false,
            ])
            ->add('odometerKm', IntegerType::class, ['label' => 'report.odometer', 'required' => false, 'attr' => ['min' => 0, 'inputmode' => 'numeric']])
            ->add('softwareVersion', TextType::class, ['label' => 'report.software_version', 'required' => false, 'help' => 'report.software_version.help'])
            ->add('description', TextareaType::class, ['label' => 'report.description', 'required' => false, 'attr' => ['rows' => 5, 'placeholder' => 'report.description.placeholder']])
            // Footage
            ->add('footage', DropzoneType::class, [
                'label' => 'report.footage',
                'required' => false,
                'help' => 'report.footage.help',
                'placeholder' => 'report.footage.placeholder',
                'remove_label' => 'report.photos.remove',
                'attr' => ['accept' => 'video/*', 'title' => ''], // empty title hides the browser's "No file chosen" tooltip
            ])
            ->add('photos', DropzoneType::class, [
                'label' => 'report.photos',
                'required' => false,
                'multiple' => true,
                'max_files' => Incident::MAX_PHOTOS, // also adds a Count constraint
                'help' => 'report.photos.help',
                'help_translation_parameters' => ['max' => Incident::MAX_PHOTOS],
                'placeholder' => 'report.photos.placeholder',
                'remove_label' => 'report.photos.remove',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp', 'title' => ''],
            ])
            ->add('photoMasks', HiddenType::class, ['required' => false, 'attr' => ['data-photo-masks-target' => 'field']])
            ->add('privacyConsent', CheckboxType::class, [
                'label' => 'report.privacy_consent',
                'label_html' => true,
                'label_translation_parameters' => [
                    'privacy' => $this->urls->generate('legal', ['page' => 'privacy']),
                    'terms' => $this->urls->generate('legal', ['page' => 'terms']),
                ],
                'required' => true,
            ]);

        $builder->get('latitude')->addModelTransformer(new NullableFloatTransformer());
        $builder->get('longitude')->addModelTransformer(new NullableFloatTransformer());
    }

    /** @return array<string, string> localised name → code, alphabetical */
    private function europeanCountries(string $locale): array
    {
        $choices = [];
        foreach (IncidentCountry::EUROPE as $code) {
            $choices[Countries::getName($code, $locale)] = $code;
        }
        ksort($choices, \SORT_LOCALE_STRING);

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReportIncidentData::class,
            'translation_domain' => 'messages',
            'countries' => Country::cases(),
            'locale' => 'en',
        ]);
        $resolver->setAllowedTypes('locale', 'string');
        $resolver->setAllowedTypes('countries', Country::class.'[]');
    }
}

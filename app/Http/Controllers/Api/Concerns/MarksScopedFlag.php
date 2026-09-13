<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Renseigne un drapeau booléen PAR LOT sur une collection de modèles, via un
 * Local Scope Eloquent, en UNE requête pour toute la page — jamais de
 * comparaison PHP sur une valeur fraîchement écrite (piège timezone du
 * dépôt : une ligne insérée/relue dans la même requête peut paraître décalée
 * du fuseau). Factorise un pattern qui revenait dans trois contrôleurs
 * (`BookingController::markStartsLater`, `PresenceController::markEditability`,
 * `DeskController::markCancellable` — review lot E pt.7).
 */
trait MarksScopedFlag
{
    /**
     * @template TModel of Model
     *
     * @param  iterable<int, TModel>  $models
     * @param  string  $scope  nom du Local Scope Eloquent (sans le préfixe `scope`)
     * @param  string  $property  propriété publique à renseigner sur chaque modèle
     */
    private function markWithScope(iterable $models, string $scope, string $property): void
    {
        $models = collect($models)->all();

        if ($models === []) {
            return;
        }

        /** @var TModel $first */
        $first = reset($models);
        $modelClass = $first::class;

        $matchedIds = $modelClass::query()
            ->whereKey(array_map(static fn (Model $model): int|string => $model->getKey(), $models))
            ->{$scope}()
            ->pluck($first->getKeyName())
            ->all();

        foreach ($models as $model) {
            $model->{$property} = in_array($model->getKey(), $matchedIds, true);
        }
    }
}

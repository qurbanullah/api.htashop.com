<?php

namespace Tests\Feature\Tag;

use App\Actions\Tags\AttachTagsAction;
use App\Actions\Tags\CreateTagAction;
use App\Actions\Tags\DetachTagsAction;
use App\Actions\Tags\GetPopularTagsAction;
use App\Actions\Tags\GetTagsAction;
use App\Actions\Tags\SyncTagsAction;
use App\Actions\Tags\TagCreateAction;
use App\Actions\Tags\TagDeleteAction;
use App\Actions\Tags\TagPatchAction;
use App\Actions\Tags\TagReadAction;
use App\Actions\Tags\TagSearchByIdAction;
use App\Actions\Tags\TagShowAction;
use App\Actions\Tags\TagSortAction;
use App\Actions\Tags\TagUpdateAction;

test('the Tag action classes exist', function () {
    expect(class_exists(AttachTagsAction::class))->toBeTrue();
    expect(class_exists(CreateTagAction::class))->toBeTrue();
    expect(class_exists(DetachTagsAction::class))->toBeTrue();
    expect(class_exists(GetPopularTagsAction::class))->toBeTrue();
    expect(class_exists(GetTagsAction::class))->toBeTrue();
    expect(class_exists(SyncTagsAction::class))->toBeTrue();
    expect(class_exists(TagCreateAction::class))->toBeTrue();
    expect(class_exists(TagDeleteAction::class))->toBeTrue();
    expect(class_exists(TagPatchAction::class))->toBeTrue();
    expect(class_exists(TagReadAction::class))->toBeTrue();
    expect(class_exists(TagSearchByIdAction::class))->toBeTrue();
    expect(class_exists(TagShowAction::class))->toBeTrue();
    expect(class_exists(TagSortAction::class))->toBeTrue();
    expect(class_exists(TagUpdateAction::class))->toBeTrue();
});

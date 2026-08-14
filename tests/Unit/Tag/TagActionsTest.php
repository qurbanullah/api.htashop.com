<?php

namespace Tests\Unit\Tag;

test('the Tag action classes exist', function () {
    expect(class_exists(\App\Actions\Tags\AttachTagsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\CreateTagAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\DetachTagsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\GetPopularTagsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\GetTagsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\SyncTagsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\TagCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\TagDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\TagPatchAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\TagReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\TagSearchByIdAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\TagShowAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\TagSortAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tags\TagUpdateAction::class))->toBeTrue();
});

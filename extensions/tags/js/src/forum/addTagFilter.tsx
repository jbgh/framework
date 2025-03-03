import app from 'flarum/forum/app';
import type Mithril from 'mithril';
import { extend, override } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import DiscussionListState from 'flarum/forum/states/DiscussionListState';
import GlobalSearchState from 'flarum/forum/states/GlobalSearchState';
import classList from 'flarum/common/utils/classList';
import textContrastClass from 'flarum/common/helpers/textContrastClass';
import type { ComponentAttrs } from 'flarum/common/Component';

import TagHero from './components/TagHero';
import type Tag from '../common/models/Tag';

const findTag = (slug: string) => app.store.all<Tag>('tags').find((tag) => tag.slug().localeCompare(slug, undefined, { sensitivity: 'base' }) === 0);

export default function addTagFilter() {
  app.currentTag = function (reload?: boolean) {
    if (this.currentActiveTag && !reload) {
      return this.currentActiveTag;
    }

    const slug = this.search.params().tags;
    let tag = null;

    if (slug) {
      tag = findTag(slug);
    }

    if ((slug && !tag) || (tag && !tag.isChild() && !tag.children())) {
      if (this.currentTagLoading) {
        return;
      }

      this.currentTagLoading = true;

      // Unlike the backend, no need to fetch parent.children because if we're on
      // a child tag page, then either:
      //    - We loaded in that child tag (and its siblings) in the API document
      //    - We first navigated to the current tag's parent, which would have loaded in the current tag's siblings.
      this.store
        .find('tags', slug, { include: 'children,children.parent,parent,state' })
        .then(() => {
          this.currentActiveTag = findTag(slug);

          m.redraw();
        })
        .finally(() => {
          this.currentTagLoading = false;
        });
    }

    if (tag) {
      this.currentActiveTag = tag;
      return this.currentActiveTag;
    }

    this.currentActiveTag = undefined;

    return;
  };

  extend(IndexPage.prototype, 'view', function (vdom: Mithril.Vnode<ComponentAttrs, {}>) {
    app.currentTag(true);
  });

  // If currently viewing a tag, insert a tag hero at the top of the view.
  override(IndexPage.prototype, 'hero', function (original) {
    const tag = app.currentTag();

    if (tag) return <TagHero model={tag} />;

    return original();
  });

  extend(IndexPage.prototype, 'view', function (vdom: Mithril.Vnode<ComponentAttrs, {}>) {
    const tag = app.currentTag();

    if (tag) vdom.attrs.className += ' IndexPage--tag' + tag.id();
  });

  extend(IndexPage.prototype, 'setTitle', function () {
    const tag = app.currentTag();

    if (tag) {
      app.setTitle(tag.name());
    }
  });

  // If currently viewing a tag, restyle the 'new discussion' button to use
  // the tag's color, and disable if the user isn't allowed to edit.
  extend(IndexSidebar.prototype, 'items', function (items) {
    const tag = app.currentTag();

    if (tag) {
      const color = tag.color();
      const canStartDiscussion = tag.canStartDiscussion() || !app.session.user;
      const newDiscussion = items.get('newDiscussion') as Mithril.Vnode<ComponentAttrs, {}>;

      if (color) {
        newDiscussion.attrs.className = classList([newDiscussion.attrs.className, 'Button--tagColored', textContrastClass(color)]);
        newDiscussion.attrs.style = { '--color': color };
      }

      newDiscussion.attrs.disabled = !canStartDiscussion;
      newDiscussion.children = app.translator.trans(
        canStartDiscussion ? 'core.forum.index.start_discussion_button' : 'core.forum.index.cannot_start_discussion_button'
      );
    }
  });

  // Add a parameter for the global search state to pass on to the
  // DiscussionListState that will let us filter discussions by tag.
  extend(GlobalSearchState.prototype, 'params', function (params) {
    params.tags = m.route.param('tags');
  });

  // Translate that parameter into a gambit appended to the search query.
  extend(DiscussionListState.prototype, 'requestParams', function (this: DiscussionListState, params) {
    if (typeof params.include === 'string') {
      params.include = [params.include];
    } else {
      params.include?.push('tags', 'tags.parent');
    }

    if (this.params.tags) {
      params.filter ||= {};
      params.filter.tag = this.params.tags;
    }
  });
}

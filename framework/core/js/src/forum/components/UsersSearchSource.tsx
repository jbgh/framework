import type Mithril from 'mithril';

import app from '../../forum/app';
import highlight from '../../common/helpers/highlight';
import username from '../../common/helpers/username';
import Link from '../../common/components/Link';
import { SearchSource } from './Search';
import User from '../../common/models/User';
import Avatar from '../../common/components/Avatar';

/**
 * The `UsersSearchSource` finds and displays user search results in the search
 * dropdown.
 */
export default class UsersSearchResults implements SearchSource {
  protected results = new Map<string, User[]>();

  async search(query: string): Promise<void> {
    return app.store
      .find<User[]>('users', {
        filter: { q: query },
        page: { limit: 5 },
      })
      .then((results) => {
        this.results.set(query, results);
        m.redraw();
      });
  }

  view(query: string): Array<Mithril.Vnode> {
    query = query.toLowerCase();

    const results = (this.results.get(query) || [])
      .concat(
        app.store
          .all<User>('users')
          .filter((user) => [user.username(), user.displayName()].some((value) => value.toLowerCase().substr(0, query.length) === query))
      )
      .filter((e, i, arr) => arr.lastIndexOf(e) === i)
      .sort((a, b) => a.displayName().localeCompare(b.displayName()));

    if (!results.length) return [];

    return [
      <li className="Dropdown-header">{app.translator.trans('core.forum.search.users_heading')}</li>,
      ...results.map((user) => {
        const name = username(user, (name: string) => highlight(name, query));

        return (
          <li className="UserSearchResult" data-index={'users' + user.id()}>
            <Link href={app.route.user(user)}>
              <Avatar user={user} />
              {name}
            </Link>
          </li>
        );
      }),
    ];
  }
}

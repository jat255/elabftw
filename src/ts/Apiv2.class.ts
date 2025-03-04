/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2022 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import { Method } from './interfaces';
import { notifSaved, notifError } from './misc';

export class Api {
  // set this to false to prevent the "Saved" notification from showing up
  notifOnSaved = true;

  get(query: string): Promise<Response> {
    return this.send(Method.GET, query);
  }

  getJson(query: string) {
    return this.get(query).then(resp => resp.json());
  }

  patch(query: string, params = {}): Promise<Response> {
    return this.send(Method.PATCH, query, params);
  }

  post(query: string, params = {}): Promise<Response> {
    return this.send(Method.POST, query, params);
  }

  delete(query: string): Promise<Response> {
    return this.send(Method.DELETE, query);
  }

  // private method: use patch/post/delete instead
  send(method: Method, query: string, params = {}): Promise<Response> {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const options = {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
    };
    if ([Method.POST, Method.PATCH].includes(method)) {
      options['body'] = JSON.stringify(params);
    }
    return fetch(`api/v2/${query}`, options).then(response => {
      if (response.status !== this.getOkStatusFromMethod(method)) {
        // if there is an error we will get the message in the reply body
        return response.json().then(json => { throw new Error(json.description); });
      }
      return response;
    }).then(response => {
      if (method !== Method.GET && this.notifOnSaved) {
        notifSaved();
      }
      return response;
    }).catch(error => {
      notifError(error);
      return new Promise((resolve, reject) => reject(new Error(error.message)));
    });
  }

  getOkStatusFromMethod(method: Method): number {
    switch (method) {
    case Method.GET:
    case Method.PATCH:
      return 200;
    case Method.POST:
      return 201;
    case Method.DELETE:
      return 204;
    default:
      return 200;
    }
  }

}

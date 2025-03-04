/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import { Model, Todoitem, EntityType, UnfinishedEntities } from './interfaces';
import SidePanel from './SidePanel.class';
import { relativeMoment, makeSortableGreatAgain } from './misc';
import FavTag from './FavTag.class';
import i18next from 'i18next';
import { Api } from './Apiv2.class';

export default class Todolist extends SidePanel {

  unfinishedStepsScope: string;
  initialLoad = true;
  api: Api;

  constructor() {
    super(Model.Todolist);
    this.panelId = 'todolistPanel';
    this.unfinishedStepsScope = 'user';
    this.api = new Api();
  }

  create(content: string): Promise<Response> {
    return this.api.post(`${this.model}`, {'content': content});
  }

  readAll() {
    return this.api.getJson(`${this.model}`);
  }

  display(): Promise<void> {
    return this.readAll().then(json => {
      let html = '';
      for (const entry of json as Array<Todoitem>) {
        html += `<li data-todoitemid=${entry.id} id='todoItem_${entry.id}'>
        <a class='float-right mr-2' data-action='destroy-todoitem' data-todoitemid='${entry.id}' title='` + i18next.t('generic-delete-warning') + `'>
          <i class='fas fa-trash-alt'></i>
        </a>
        <span style='font-size:90%;display:block;'><span class='draggable sortableHandle'><i class='fas fa-sort'></i></span> <span class='relative-moment' title='${entry.creation_time}'></span></span>
        <span class='todoItem editable' data-todoitemid='${entry.id}'>${entry.body}</span>
      </li>`;
      }
      document.getElementById('todoItems').innerHTML = html;
      makeSortableGreatAgain();
      relativeMoment();
    });
  }

  update(id: number, content: string): Promise<Response> {
    return this.api.patch(`${this.model}/${id}`, {'content': content});
  }

  toggleUnfinishedStepsScope(): void {
    localStorage.setItem(this.model + 'StepsShowTeam', (localStorage.getItem(this.model + 'StepsShowTeam') === '1' ? '0' : '1'));
    this.unfinishedStepsScope = (this.unfinishedStepsScope === 'user' ? 'team' : 'user');
    this.loadUnfinishedStep();
  }

  loadUnfinishedStep(): void  {
    this.getUnfinishedStep(EntityType.Experiment);
    this.getUnfinishedStep(EntityType.Item);
  }

  getUnfinishedStep(type: EntityType): Promise<void> {
    return this.api.getJson(`unfinished_steps?scope=${this.unfinishedStepsScope}`).then(json => {
      let html = '';
      for (const entity of json[type] as Array<UnfinishedEntities>) {
        html += `<li><p><a href='${type === EntityType.Item ? 'database' : 'experiments'}.php?mode=view&id=${entity.id}'>${entity.title}</a></p>`;
        for (const stepsData of Object.entries(entity.steps)) {
          const stepId = stepsData[1][0];
          const stepBody = stepsData[1][1];
          html += `<div><input type='checkbox' class='stepbox mr-2' id='todo_step_${stepId}' data-id='${entity.id}' data-type='${type}' data-stepid='${stepId}' />${stepBody}</div>`;
        }
        html += '</li>';
      }
      const typeIdName = 'todoSteps' + type.charAt(0).toUpperCase() + type.slice(1);
      document.getElementById(typeIdName).innerHTML = html;
    });
  }

  // TOGGLE TODOLIST VISIBILITY
  toggle(): void {
    if (!document.getElementById('favtagsPanel').hasAttribute('hidden')) {
      document.getElementById('favtags-opener').removeAttribute('hidden');
    }
    // force favtags to close if it's open
    (new FavTag).hide();
    super.toggle();
    // lazy load content only once
    if (!document.getElementById(this.panelId).hasAttribute('hidden') && this.initialLoad) {
      this.display();
      this.loadUnfinishedStep();
      this.initialLoad = false;
    }
  }

  destroy(id: number): Promise<Response> {
    return this.api.delete(`${this.model}/${id}`);
  }
}

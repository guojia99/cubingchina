<?php $events = $competition->getEventsRounds(); ?>
<?php $params = $competition->getLastActiveEventRound($events); ?>
<?php echo CHtml::tag('div', array(
  'id'=>'live-container',
  'data-competition-id'=>$competition->id,
  'data-events'=>json_encode($events),
  'data-params'=>json_encode($params),
  'data-user'=>json_encode(array(
    'isGuest'=>Yii::app()->user->isGuest,
    'isOrganizer'=>!Yii::app()->user->isGuest && $this->user->isOrganizer() && isset($competition->organizers[$this->user->id]),
    'isDelegate'=>!Yii::app()->user->isGuest && $this->user->isDelegate() && isset($competition->delegates[$this->user->id]),
    'isAdmin'=>Yii::app()->user->checkRole(User::ROLE_ADMINISTRATOR),
    'name'=>Yii::app()->user->isGuest ? '' : $this->user->getCompetitionName(),
  )),
  'v-cloak'=>true,
), ''); ?>

<template id="live-container-template">
  <div class="col-lg-12">
    <div class="options-area">
      <div class="pull-right">
        <button class="btn btn-md btn-warning no-mr" @click="showOptions">
          <i class="fa fa-gear"></i>
        </button>
      </div>
      <div tabindex="-1" id="options-modal" class="modal fade">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-body">
              <div class="checkbox" v-if="hasPermission">
                <label>
                  <input type="checkbox" v-model="options.enableEntry"> <?php echo Yii::t('live', 'Enable Data Entry'); ?>
                </label>
              </div>
              <div class="checkbox">
                <label>
                  <input type="checkbox" v-model="options.showMessage"> <?php echo Yii::t('live', 'Show Message on Chat'); ?>
                </label>
              </div>
              <div class="checkbox">
                <label>
                  <input type="checkbox" v-model="options.alertResult"> <?php echo Yii::t('live', 'Show Result on Chat'); ?>
                </label>
              </div>
              <div class="checkbox">
                <label>
                  <input type="checkbox" v-model="options.alertRecord"> <?php echo Yii::t('live', 'Show Record on Chat'); ?>
                </label>
              </div>
            </div>
            <div class="modal-footer">
              <button data-dismiss="modal" class="btn btn-default" type="button"><?php echo Yii::t('common', 'Close'); ?></button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <chat :options="options" v-if="options.showMessage || options.alertResult || options.alertRecord"></chat>
    <result :options="options"></result>
  </div>
</template>

<template id="chat-template">
  <div class="panel panel-info">
    <div class="panel-body">
      <div class="message-container">
        <ul class="unstyled">
          <li v-for="message in messages">
            <message :message="message"></message>
          </li>
        </ul>
      </div>
      <div class="chat-input-panel">
        <div class="col-sm-10 col-lg-11">
          <input v-model="message" class="form-control" @keyup.enter="send" :disabled="$store.state.user.isGuest || !options.showMessage" placeholder="<?php echo Yii::app()->user->isGuest ? Yii::t('common', 'Please login.') : ''; ?>" />
        </div>
        <div class="col-sm-2 col-lg-1">
          <button class="btn btn-primary btn-md form-control" @click="send" :disabled="$store.state.user.isGuest || !options.showMessage || message == ''"><?php echo Yii::t('common', 'Submit'); ?></button>
        </div>
      </div>
    </div>
  </div>
</template>

<template id="message-template">
  <div class="chat-message" :class="{'self-message': message.isSelf}">
    <div class="message-meta">
      {{message.user.name}} {{message.time | formatTime}}
    </div>
    <div class="message-body">
      {{{message.content}}}
    </div>
  </div>
</template>

<template id="result-template">
  <div class="row">
    <div class="col-md-3 col-sm-4" v-if="enableEntry">
      <input-panel :result.sync="current"></input-panel>
    </div>
    <div class="col-md-{{enableEntry ? 9 : 12}} col-sm-{{enableEntry ? 8 : 12}}">
      <div tabindex="-1" id="round-settings-modal" class="modal fade">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-body">
              <div class="form-group">
                <label><?php echo Yii::t('Schedule', 'Cut Off'); ?></label>
                <input type="tel" class="form-control" id="cut_off" v-model="cut_off">
              </div>
              <div class="form-group">
                <label><?php echo Yii::t('Schedule', 'Time Limit'); ?></label>
                <input type="tel" class="form-control" id="time_limit" v-model="time_limit">
              </div>
              <div class="form-group">
                <label><?php echo Yii::t('Schedule', 'Number'); ?></label>
                <input type="tel" class="form-control" id="number" v-model="number">
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-success" type="button" @click="saveRoundSettings"><?php echo Yii::t('live', 'Save'); ?></button>
              <button data-dismiss="modal" class="btn btn-default" type="button"><?php echo Yii::t('common', 'Close'); ?></button>
            </div>
          </div>
        </div>
      </div>
      <div class="clearfix">
        <h4 class="pull-left">
          {{eventName}} - {{roundName}}
          <button type="button"
            class="btn btn-sm btn-warning no-mr"
            v-if="hasPermission && options.enableEntry"
            @click="showRoundSettings"
          >
            <i class="fa fa-gear"></i>
          </button>
        </h4>
        <div class="pull-right event-round-area">
          <select @change="changeEventRound" v-model="eventRound">
            <optgroup v-for="event in events" :label="event.name">
              <option v-for="round in event.rounds" :value="{event: event.id, round: round.id}">
                {{event.name}} - {{round.name}}{{round.status != 0 ? ' - ' + round.allStatus[round.status] : ''}}
              </option>
            </optgroup>
          </select>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered table-condensed table-hover table-boxed">
          <thead>
            <th v-if="hasPermission"></th>
            <?php $columns = array(
              array(
                'name'=>Yii::t('Results', 'Place'),
                'value'=>'$data->pos',
                'htmlOptions'=>array('class'=>'place'),
              ),
              array(
                'name'=>Yii::t('Results', 'Person'),
                'value'=>'Persons::getLinkByNameNId($data->personName, $data->personId)',
              ),
              array(
                'name'=>Yii::t('common', 'Best'),
                'value'=>'$data->getTime("best")',
                'htmlOptions'=>array('class'=>'result'),
              ),
              array(
                'name'=>'',
                'value'=>'$data->regionalSingleRecord',
                'htmlOptions'=>array('class'=>'record'),
              ),
              array(
                'name'=>Yii::t('common', 'Average'),
                'value'=>'$data->getTime("average")',
                'htmlOptions'=>array('class'=>'result'),
              ),
              array(
                'name'=>'',
                'value'=>'$data->regionalAverageRecord',
                'htmlOptions'=>array('class'=>'record'),
              ),
              array(
                'name'=>Yii::t('common', 'Region'),
                'value'=>'Region::getIconName($data->person->country->name, $data->person->country->iso2)',
                'htmlOptions'=>array('class'=>'region'),
              ),
              array(
                'name'=>Yii::t('common', 'Detail'),
                'value'=>'$data->detail',
              ),
            ); ?>
            <?php foreach ($columns as $column): ?>
            <?php echo CHtml::tag('th', isset($column['htmlOptions']) ? $column['htmlOptions'] : array(), $column['name']); ?>
            <?php endforeach; ?>
          </thead>
          <tbody>
            <tr v-if="loading" class="loading">
              <td colspan="{{hasPermission ? 9 : 8}}">
                Loading...
              </td>
            </tr>
            <tr v-for="result in results" :class="{danger: result.isNew, success: isAdvanced(result)}" @dblclick="edit(result)">
              <td v-if="hasPermission">
                <button class="btn btn-xs btn-primary no-mr" @click="edit(result)"><i class="fa fa-edit"></i></button>
              </td>
              <td>{{result.pos}}</td>
              <td>
                <a href="javascript:void(0)" @click="goToUser(result.user)">{{result.user.name}}</a>
              </td>
              <td class="result">{{result.best | decodeResult result.event}}</td>
              <td class="record">{{result.regional_single_record}}</td>
              <td class="result">{{result.average | decodeResult result.event}}</td>
              <td class="record">{{result.regional_average_record}}</td>
              <td>{{{result.user.region}}}</td>
              <td>
                {{result.value1 | decodeResult result.event '--'}}&nbsp;&nbsp;&nbsp;&nbsp;
                {{result.value2 | decodeResult result.event '--'}}&nbsp;&nbsp;&nbsp;&nbsp;
                {{result.value3 | decodeResult result.event '--'}}&nbsp;&nbsp;&nbsp;&nbsp;
                {{result.value4 | decodeResult result.event '--'}}&nbsp;&nbsp;&nbsp;&nbsp;
                {{result.value5 | decodeResult result.event '--'}}&nbsp;&nbsp;&nbsp;&nbsp;
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<template id="input-panel-template">
  <div data-spy="affix" data-offset-top="550" style="top:20px">
    <div class="panel panel-theme input-panel">
      <div class="panel-heading">
        <h3 class="panel-title"><?php echo Yii::t('live', 'Input Panel'); ?></h3>
      </div>
      <div class="panel-body">
        <label for="input-panel-name"><?php echo Yii::t('common', 'Competitor'); ?></label> {{competitor && competitor.name}}
        <div class="input-wrapper">
          <div class="input-group">
            <span class="input-group-addon">No.</span>
            <input type="text"
              id="input-panel-name"
              class="form-control"
              placeholder="请输入编号或姓名"
              v-model="searchText"
              @keydown.enter="enter"
              @keydown.down="down"
              @keydown.up="up"
              @focus="searching = true"
              @blur="searching = false"
            >
          </div>
          <ul class="competitors list-group" :class="{hide: !searching}">
            <li v-for="result in competitors"
              class="list-group-item"
              :class="{active: selectedIndex == $index}"
              @mousedown.prevent="selectCompetitor(result)"
              @mouseenter="selectedIndex = $index"
            >
              <b class="number">No.{{result.number}}</b>{{result.user.name}}
            </li>
          </ul>
        </div>
        <label><?php echo Yii::t('common', 'Results'); ?></label>
        <div class="input-panel-result">
          <result-input v-for="i in inputNum"
            :value.sync="result['value' + (i + 1)]"
            :index="i"
          ></result-input>
        </div>
        <button type="button"
          id="save"
          class="btn btn-md btn-success"
          @click="save"
          @keydown.prevent="keydown"
          :disabled="result == null || result.id == null"
        ><?php echo Yii::t('live', 'Save'); ?></button>
      </div>
    </div>
  </div>
</template>

<template id="result-input-template">
  <div class="input-group">
    <span class="input-group-addon">{{index + 1}}.</span>
    <template v-if="event == '333mbf'">
      <div class="form-control result-input-wrapper">
        <div class="result-input-wrapper col-xs-5"
          :class="{active: index == $parent.currentIndex && subIndex == 0, disabled: $parent.isDisabled(index)}"
        >
          <input class="result-input" type="tel"
            id="result-input-solved-{{index}}"
            v-model="solved"
            @focus="focus(0)"
            @blur="blur"
            @keydown.prevent="keydown($event, 'solved')"
            :disabled="$parent.isDisabled(index)"
          >
          <label for="result-input-solved-{{index}}">
            <span class="number-group" v-if="time != 'DNF' && time != 'DNS'">
              <span class="number" :class="{active: solved.length > 1}">{{solved.charAt(solved.length - 2) || 0}}</span>
              <span class="number" :class="{active: solved.length > 0}">{{solved.charAt(solved.length - 1) || 0}}</span>
            </span>
            <span class="penalty" v-else>{{time}}</span>
          </label>
        </div>
        <div class="result-input-wrapper col-xs-2":class="{disabled: $parent.isDisabled(index)}">
          <label class="text-center">
            <span>/</span>
          </label>
        </div>
        <div class="result-input-wrapper col-xs-5"
          :class="{active: index == $parent.currentIndex && subIndex == 1, disabled: $parent.isDisabled(index)}"
        >
          <input class="result-input" type="tel"
            id="result-input-tried-{{index}}"
            v-model="tried"
            @focus="focus(1)"
            @blur="blur"
            @keydown.prevent="keydown($event, 'tried')"
            :disabled="$parent.isDisabled(index)"
          >
          <label for="result-input-tried-{{index}}" class="text-left">
            <span class="number-group" v-if="time != 'DNF' && time != 'DNS'">
              <span class="number" :class="{active: tried.length > 1}">{{tried.charAt(tried.length - 2) || 0}}</span>
              <span class="number" :class="{active: tried.length > 0}">{{tried.charAt(tried.length - 1) || 0}}</span>
            </span>
            <span class="penalty" v-else>{{time}}</span>
          </label>
        </div>
      </div>
    </template>
    <div class="result-input-wrapper form-control"
      :class="{active: index == $parent.currentIndex && subIndex == 2, disabled: $parent.isDisabled(index)}"
    >
      <input class="result-input" type="tel"
        id="result-input-{{index}}"
        v-model="time"
        @focus="focus(2)"
        @blur="blur"
        @keydown.prevent="keydown($event, 'time')"
        :disabled="$parent.isDisabled(index)"
      >
      <label for="result-input-{{index}}" :class="{'text-center': event === '333mbf'}">
        <span class="number-group" v-if="time != 'DNF' && time != 'DNS'">
          <span class="number" :class="{active: time.length > 5}" v-if="event != '333fm' && event !='333mbf'">{{time.charAt(time.length - 6) || 0}}</span>
          <span class="number" :class="{active: time.length > 4}" v-if="event != '333fm' && event !='333mbf'">{{time.charAt(time.length - 5) || 0}}</span>
          <span class="number" :class="{active: time.length > 4}" v-if="event != '333fm' && event !='333mbf'">:</span>
          <span class="number" :class="{active: time.length > 3}" v-if="event != '333fm'">{{time.charAt(time.length - 4) || 0}}</span>
          <span class="number" :class="{active: time.length > 2}" v-if="event != '333fm'">{{time.charAt(time.length - 3) || 0}}</span>
          <span class="number" :class="{active: time.length > 2}" v-if="event != '333fm'">{{event !='333mbf' ? '.' : ':'}}</span>
          <span class="number" :class="{active: time.length > 1}">{{time.charAt(time.length - 2) || 0}}</span>
          <span class="number" :class="{active: time.length > 0}">{{time.charAt(time.length - 1) || 0}}</span>
        </span>
        <span class="penalty" v-else>{{time}}</span>
      </label>
    </div>
  </div>
</template>
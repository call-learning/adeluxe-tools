import Modal from 'core/modal';
import Templates from 'core/templates';
import {getString} from 'core/str';

export const open = async(context = {}) => {
    const title = await getString('clapi_modal_title', '__COMPONENT__');
    const body = await Templates.render('__COMPONENT__/local/clapi/modal/body', context);

    const modal = await Modal.create({
        title,
        body,
        show: true,
    });

    return modal;
};
